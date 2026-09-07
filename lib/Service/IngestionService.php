<?php

declare(strict_types=1);

namespace OCA\MiniCRM\Service;

use DateTime;
use DateTimeZone;
use OCA\MiniCRM\Db\Activity;
use OCA\MiniCRM\Db\ActivityMapper;
use OCA\MiniCRM\Db\Client;
use OCA\MiniCRM\Db\ClientMapper;
use OCA\MiniCRM\Db\Identity;
use OCA\MiniCRM\Db\IdentityMapper;
use OCA\MiniCRM\Db\Message;
use OCA\MiniCRM\Db\MessageMapper;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class IngestionService {
    public function __construct(
        private ClientMapper $clientMapper,
        private ActivityMapper $activityMapper,
        private IdentityMapper $identityMapper,
        private MessageMapper $messageMapper,
        private PhoneNormalizer $phoneNormalizer,
        private FolderService $folderService,
        private CalendarBridgeService $calendarBridge,
        private DeckBridgeService $deckBridge,
        private ContactBridgeService $contactBridge,
        private IDBConnection $db,
        private LoggerInterface $logger
    ) {}

    /**
     * Atomically process incoming lead from Easypoint via n8n.
     *
     * @param array{
     *     client_name: string,
     *     phone?: string,
     *     email?: string,
     *     meeting_datetime?: string,
     *     meeting_timezone?: string,
     *     responsible_specialist?: string,
     *     source?: string,
     *     notes?: string,
     *     board_id?: int,
     *     stack_id?: int,
     *     channel_identity?: array{channel: string, external_id: string}
     * } $data
     * @return array<string, mixed>
     */
    public function ingestLead(array $data): array {
        $this->db->beginTransaction();

        try {
            $clientName = trim($data['client_name'] ?? 'Новый клиент');
            $rawPhone = $data['phone'] ?? null;
            $phoneNormalized = $this->phoneNormalizer->normalize($rawPhone);
            $email = !empty($data['email']) ? strtolower(trim((string)$data['email'])) : null;
            $responsibleUser = $data['responsible_specialist'] ?? 'admin';
            $source = $data['source'] ?? 'easypoint';
            $notes = $data['notes'] ?? '';

            // 1. Resolve or Create Client
            $isNewClient = false;
            $client = $this->clientMapper->findByPhoneOrEmail($phoneNormalized, $email);

            if ($client === null) {
                $isNewClient = true;
                $client = new Client();
                $client->setUuid($this->generateUuid());
                $client->setFullName($clientName);
                $client->setPhone($phoneNormalized);
                $client->setPhoneRaw($rawPhone);
                $client->setEmail($email);
                $client->setNotes($notes);
                $client->setCreatedAt(new DateTime('now'));
                $client->setUpdatedAt(new DateTime('now'));
                $client = $this->clientMapper->insert($client);
            } else {
                // Update client info if updated
                if (empty($client->getEmail()) && !empty($email)) {
                    $client->setEmail($email);
                }
                if (empty($client->getPhone()) && !empty($phoneNormalized)) {
                    $client->setPhone($phoneNormalized);
                }
                $client->setUpdatedAt(new DateTime('now'));
                $client = $this->clientMapper->update($client);
            }

            // 2. Prepare Activity
            $activityUuid = $this->generateUuid();

            // 3. Setup Folders and Public File Drop Link
            $folderInfo = $this->folderService->setupClientAndActivityFolder(
                $client->getFullName(),
                $client->getUuid(),
                $activityUuid,
                $responsibleUser
            );

            // Update client folder path if not set
            if (empty($client->getFolderPath())) {
                $client->setFolderPath($folderInfo['folder_path']);
                $this->clientMapper->update($client);
            }

            // 4. Parse Meeting Date/Time (Timezone America/Edmonton)
            $meetingDateTime = null;
            $meetingStr = $data['meeting_datetime'] ?? null;
            if (!empty($meetingStr)) {
                $tzString = $data['meeting_timezone'] ?? 'America/Edmonton';
                try {
                    $tz = new DateTimeZone($tzString);
                    $meetingDateTime = new DateTime($meetingStr, $tz);
                } catch (\Exception $e) {
                    $meetingDateTime = new DateTime($meetingStr);
                }
            }

            // 5. Calendar Event Booking
            $calendarEventId = null;
            if ($meetingDateTime !== null) {
                $eventTitle = sprintf('Встреча: %s (%s)', $client->getFullName(), $client->getPhone());
                $eventDesc = sprintf(
                    "Клиент: %s\nТелефон: %s\nEmail: %s\nПапка клиента: %s\nСсылка для загрузки (FileDrop): %s\nЗаметки: %s",
                    $client->getFullName(),
                    $client->getPhone(),
                    $client->getEmail() ?? '—',
                    $folderInfo['activity_folder_path'],
                    $folderInfo['file_drop_url'],
                    $notes
                );

                $calendarEventId = $this->calendarBridge->createEvent(
                    $responsibleUser,
                    $eventTitle,
                    $meetingDateTime,
                    60, // 1 hour duration
                    $eventDesc
                );
            }

            // 6. Nextcloud Deck Task Creation
            $deckTaskId = null;
            $cardTitle = sprintf('%s - %s', $client->getFullName(), $source);
            $cardDesc = sprintf(
                "## Заявка из %s\n\n" .
                "- **Клиент:** %s\n" .
                "- **Телефон:** %s\n" .
                "- **Email:** %s\n" .
                "- **Дата встречи:** %s\n" .
                "- **Папка документов:** `%s`\n" .
                "- **Ссылка клиенту (FileDrop):** [Загрузка файлов](%s)\n\n" .
                "### Примечания:\n%s",
                strtoupper($source),
                $client->getFullName(),
                $client->getPhone(),
                $client->getEmail() ?? '—',
                $meetingDateTime ? $meetingDateTime->format('Y-m-d H:i (T)') : 'Не назначена',
                $folderInfo['activity_folder_path'],
                $folderInfo['file_drop_url'],
                $notes ?: 'Нет'
            );

            $boardId = isset($data['board_id']) ? (int)$data['board_id'] : null;
            $stackId = isset($data['stack_id']) ? (int)$data['stack_id'] : null;

            $deckTaskId = $this->deckBridge->createCard(
                $cardTitle,
                $cardDesc,
                $responsibleUser,
                $meetingDateTime,
                $boardId,
                $stackId
            );

            // 6b. Sync Contact into Nextcloud Contacts module
            $contactInfo = null;
            try {
                $contactInfo = $this->contactBridge->syncContact(
                    $responsibleUser,
                    $client,
                    $folderInfo['folder_path'] ?? null
                );
            } catch (\Exception $e) {
                $this->logger->error('Error syncing contact to Nextcloud Contacts: ' . $e->getMessage(), ['app' => 'minicrm']);
            }

            // 7. Save Activity Record
            $activity = new Activity();
            $activity->setActivityUuid($activityUuid);
            $activity->setClientId((int)$client->getId());
            $activity->setSource($source);
            $activity->setStatus('scheduled');
            $activity->setResponsibleUser($responsibleUser);
            $activity->setDeckTaskId($deckTaskId);
            $activity->setCalendarEventId($calendarEventId);
            $activity->setMeetingTime($meetingDateTime);
            $activity->setFileDropUrl($folderInfo['file_drop_url']);
            $activity->setCreatedAt(new DateTime('now'));
            $activity->setUpdatedAt(new DateTime('now'));
            $activity = $this->activityMapper->insert($activity);

            // 8. Optional: Save Identity (Telegram / WhatsApp / Facebook)
            if (!empty($data['channel_identity']) && is_array($data['channel_identity'])) {
                $channel = $data['channel_identity']['channel'] ?? '';
                $extId = $data['channel_identity']['external_id'] ?? '';
                if (!empty($channel) && !empty($extId)) {
                    $existingIdent = $this->identityMapper->findByChannelAndExternalId($channel, $extId);
                    if ($existingIdent === null) {
                        $ident = new Identity();
                        $ident->setClientId((int)$client->getId());
                        $ident->setChannel($channel);
                        $ident->setExternalId($extId);
                        $ident->setCreatedAt(new DateTime('now'));
                        $this->identityMapper->insert($ident);
                    }
                }
            }

            // 9. Initial System Message in Timeline
            $sysMsg = new Message();
            $sysMsg->setClientId((int)$client->getId());
            $sysMsg->setActivityId((int)$activity->getId());
            $sysMsg->setChannel('system');
            $sysMsg->setDirection('inbound');
            $sysMsg->setSenderRecipient($source);
            $sysMsg->setSubject('Новая встреча забронирована');
            $sysMsg->setContent(sprintf(
                "Встреча назначена на %s. Созданы карточка Deck #%s и папка для документов.",
                $meetingDateTime ? $meetingDateTime->format('Y-m-d H:i') : 'уточняется',
                $deckTaskId ?? '—'
            ));
            $sysMsg->setCreatedAt(new DateTime('now'));
            $this->messageMapper->insert($sysMsg);

            $this->db->commit();

            return [
                'status' => 'success',
                'is_new_client' => $isNewClient,
                'client' => $client->jsonSerialize(),
                'activity' => $activity->jsonSerialize(),
                'file_drop_url' => $folderInfo['file_drop_url'],
                'folder_path' => $folderInfo['activity_folder_path'],
                'deck_task_id' => $deckTaskId,
                'calendar_event_id' => $calendarEventId,
                'contact' => $contactInfo,
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger->error('Failed to ingest lead: ' . $e->getMessage(), [
                'app' => 'minicrm',
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function generateUuid(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
