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
            // 1. Client Name mapping (support Easy!Appointments customer_first_name + customer_last_name)
            $firstName = trim((string)($data['customer_first_name'] ?? $data['first_name'] ?? ''));
            $lastName = trim((string)($data['customer_last_name'] ?? $data['last_name'] ?? ''));
            $nameFromParts = trim($firstName . ' ' . $lastName);

            $clientName = trim((string)($data['client_name'] ?? ''));
            if (empty($clientName) && !empty($nameFromParts)) {
                $clientName = $nameFromParts;
            }
            if (empty($clientName)) {
                $clientName = 'Новый клиент';
            }

            // 2. Phone mapping (support customer_phone)
            $rawPhone = $data['phone'] ?? $data['customer_phone'] ?? $data['customer_phone_number'] ?? null;
            $phoneNormalized = $this->phoneNormalizer->normalize($rawPhone ? (string)$rawPhone : null);

            // 3. Email mapping (support customer_email)
            $rawEmail = $data['email'] ?? $data['customer_email'] ?? null;
            $email = !empty($rawEmail) ? strtolower(trim((string)$rawEmail)) : null;

            // 4. Address mapping (support customer_address, customer_city, customer_zip_code)
            $street = trim((string)($data['customer_address'] ?? $data['address'] ?? $data['street'] ?? ''));
            $city = trim((string)($data['customer_city'] ?? $data['city'] ?? ''));
            $postalCode = trim((string)($data['customer_zip_code'] ?? $data['customer_zip'] ?? $data['postal_code'] ?? $data['zip'] ?? ''));
            $state = trim((string)($data['customer_state'] ?? $data['state'] ?? ''));
            if (empty($state) && !empty($postalCode) && strtoupper($postalCode[0]) === 'T') {
                $state = 'AB'; // Alberta default for postal codes starting with T
            }
            $country = trim((string)($data['customer_country'] ?? $data['country'] ?? 'Canada'));

            $addressArray = [
                'street' => $street,
                'city' => $city,
                'state' => $state,
                'postal_code' => $postalCode,
                'country' => $country,
            ];

            $addrDisplayParts = array_filter([$street, $city, $state, $postalCode, $country]);
            $formattedAddress = implode(', ', $addrDisplayParts);

            // 5. Notes, Service & Appointment Metadata
            $rawNotes = trim((string)($data['notes'] ?? ''));
            $aptNotes = trim((string)($data['appointment_notes'] ?? ''));
            $custNotes = trim((string)($data['customer_notes'] ?? ''));
            $serviceName = trim((string)($data['service_name'] ?? ''));
            $servicePrice = trim((string)($data['service_price'] ?? ''));
            $appointmentId = $data['appointment_id'] ?? null;

            $notesList = [];
            if (!empty($rawNotes)) {
                $notesList[] = $rawNotes;
            }
            if (!empty($aptNotes)) {
                $notesList[] = "Заметки встречи: " . $aptNotes;
            }
            if (!empty($custNotes)) {
                $notesList[] = "Заметки клиента: " . $custNotes;
            }
            if (!empty($serviceName)) {
                $srvText = "Услуга: " . $serviceName;
                if (!empty($servicePrice)) {
                    $srvText .= " ($" . $servicePrice . ")";
                }
                $notesList[] = $srvText;
            }
            if (!empty($formattedAddress)) {
                $notesList[] = "Адрес: " . $formattedAddress;
            }
            if (!empty($appointmentId)) {
                $notesList[] = "Appointment ID: #" . $appointmentId;
            }
            $notes = implode("\n", $notesList);

            // 6. Responsible specialist & Source
            $responsibleUser = $data['responsible_specialist'] ?? 'admin';
            $source = $data['source'] ?? 'easypoint';

            // 7. Resolve or Create Client
            $isNewClient = false;
            $client = $this->clientMapper->findByPhoneOrEmail($phoneNormalized, $email);

            if ($client === null) {
                $isNewClient = true;
                $client = new Client();
                $client->setUuid($this->generateUuid());
                $client->setFullName($clientName);
                $client->setPhone($phoneNormalized);
                $client->setPhoneRaw($rawPhone ? (string)$rawPhone : null);
                $client->setEmail($email);
                $client->setNotes($notes);
                $client->setCreatedAt(new DateTime('now'));
                $client->setUpdatedAt(new DateTime('now'));
                $client = $this->clientMapper->insert($client);
            } else {
                // If existing client had dummy/default name ("Новый клиент") and now we have real name
                if ($client->getFullName() === 'Новый клиент' && $clientName !== 'Новый клиент') {
                    $client->setFullName($clientName);
                }
                if (empty($client->getEmail()) && !empty($email)) {
                    $client->setEmail($email);
                }
                if (empty($client->getPhone()) && !empty($phoneNormalized)) {
                    $client->setPhone($phoneNormalized);
                }
                if (empty($client->getPhoneRaw()) && !empty($rawPhone)) {
                    $client->setPhoneRaw((string)$rawPhone);
                }
                // Append notes if new address/notes present
                if (!empty($notes)) {
                    $existingNotes = $client->getNotes() ?: '';
                    if (!empty($formattedAddress) && strpos($existingNotes, $formattedAddress) === false) {
                        $client->setNotes(trim($existingNotes . "\n\n" . $notes));
                    } elseif (empty($existingNotes)) {
                        $client->setNotes($notes);
                    }
                }
                $client->setUpdatedAt(new DateTime('now'));
                $client = $this->clientMapper->update($client);
            }

            // 8. Prepare Activity
            $activityUuid = $this->generateUuid();

            // 9. Setup Folders and Public File Drop Link
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

            // 10. Parse Meeting Date/Time (support meeting_datetime and start_datetime)
            $meetingDateTime = null;
            $meetingStr = $data['meeting_datetime'] ?? $data['start_datetime'] ?? null;
            if (!empty($meetingStr)) {
                $tzString = $data['meeting_timezone'] ?? 'America/Edmonton';
                try {
                    $tz = new DateTimeZone($tzString);
                    $meetingDateTime = new DateTime($meetingStr, $tz);
                } catch (\Exception $e) {
                    $meetingDateTime = new DateTime($meetingStr);
                }
            }

            // 11. Calendar Event Booking
            $calendarEventId = null;
            if ($meetingDateTime !== null) {
                $eventTitle = sprintf('Встреча: %s (%s)', $client->getFullName(), $client->getPhone());
                $eventDesc = sprintf(
                    "Клиент: %s\nТелефон: %s\nEmail: %s\nАдрес: %s\nУслуга: %s\nПапка клиента: %s\nСсылка для загрузки (FileDrop): %s\n\nЗаметки:\n%s",
                    $client->getFullName(),
                    $client->getPhone(),
                    $client->getEmail() ?? '—',
                    $formattedAddress ?: '—',
                    $serviceName ?: '—',
                    $folderInfo['activity_folder_path'],
                    $folderInfo['file_drop_url'],
                    $notes
                );

                $durationMinutes = isset($data['service_duration']) ? (int)$data['service_duration'] : 60;

                $calendarEventId = $this->calendarBridge->createEvent(
                    $responsibleUser,
                    $eventTitle,
                    $meetingDateTime,
                    $durationMinutes,
                    $eventDesc
                );
            }

            // 12. Nextcloud Deck Task Creation
            $deckTaskId = null;
            $cardTitle = sprintf('%s - %s', $client->getFullName(), !empty($serviceName) ? $serviceName : strtoupper($source));
            $cardDesc = sprintf(
                "## Заявка из %s\n\n" .
                "- **Клиент:** %s\n" .
                "- **Телефон:** %s\n" .
                "- **Email:** %s\n" .
                "- **Адрес:** %s\n" .
                "- **Услуга:** %s\n" .
                "- **Дата встречи:** %s\n" .
                "- **Папка документов:** `%s`\n" .
                "- **Ссылка клиенту (FileDrop):** [Загрузка файлов](%s)\n\n" .
                "### Примечания:\n%s",
                strtoupper($source),
                $client->getFullName(),
                $client->getPhone(),
                $client->getEmail() ?? '—',
                $formattedAddress ?: '—',
                $serviceName ? ($serviceName . (!empty($servicePrice) ? " ($" . $servicePrice . ")" : "")) : '—',
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

            // 13. Sync Contact into Nextcloud Contacts module WITH ADDRESS
            $contactInfo = null;
            try {
                $contactInfo = $this->contactBridge->syncContact(
                    $responsibleUser,
                    $client,
                    $folderInfo['folder_path'] ?? null,
                    $addressArray
                );
            } catch (\Throwable $e) {
                $this->logger->error('Error syncing contact to Nextcloud Contacts: ' . $e->getMessage(), ['app' => 'minicrm']);
            }

            // 14. Save Activity Record
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
            $sysMsg->setSubject('Новая встреча забронирована: ' . ($serviceName ?: 'Консультация'));
            $sysMsg->setContent(sprintf(
                "Встреча назначена на %s. Созданы карточка Deck #%s, папка для документов и контакт в Nextcloud Contacts.%s",
                $meetingDateTime ? $meetingDateTime->format('Y-m-d H:i') : 'уточняется',
                $deckTaskId ?? '—',
                !empty($formattedAddress) ? "\nАдрес: " . $formattedAddress : ""
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
        } catch (\Throwable $e) {
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
