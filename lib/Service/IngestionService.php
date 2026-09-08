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

            // 5. Notes, Service, Provider & Appointment Metadata
            $rawNotes = trim((string)($data['notes'] ?? ''));
            $aptNotes = trim((string)($data['appointment_notes'] ?? ''));
            $custNotes = trim((string)($data['customer_notes'] ?? ''));
            $serviceName = trim((string)($data['service_name'] ?? ''));
            $servicePrice = isset($data['service_price']) ? (string)$data['service_price'] : '';
            $serviceDuration = isset($data['service_duration']) ? (int)$data['service_duration'] : 60;
            $appointmentId = $data['appointment_id'] ?? null;
            $customerId = $data['customer_id'] ?? null;

            $bookDatetime = $data['book_datetime'] ?? $data['create_datetime'] ?? null;
            $startDatetime = $data['start_datetime'] ?? $data['meeting_datetime'] ?? null;
            $endDatetime = $data['end_datetime'] ?? null;
            if (empty($endDatetime) && !empty($startDatetime)) {
                try {
                    $st = new DateTime($startDatetime);
                    $endDatetime = (clone $st)->modify("+{$serviceDuration} minutes")->format('Y-m-d H:i:s');
                } catch (\Throwable) {}
            }

            // Provider mapping (support provider_first_name + provider_last_name)
            $providerFirst = trim((string)($data['provider_first_name'] ?? ''));
            $providerLast = trim((string)($data['provider_last_name'] ?? ''));
            $providerFullName = trim($providerFirst . ' ' . $providerLast);

            // Responsible specialist & Source
            $responsibleUser = !empty($data['responsible_specialist'])
                ? (string)$data['responsible_specialist']
                : (!empty($providerFullName) ? $providerFullName : 'admin');
            $source = $data['source'] ?? 'easypoint';

            $notesList = [];
            if (!empty($serviceName)) {
                $srvText = "Услуга: " . $serviceName;
                if (!empty($servicePrice)) {
                    $srvText .= " ($" . $servicePrice . ")";
                }
                if (!empty($serviceDuration)) {
                    $srvText .= " [" . $serviceDuration . " мин]";
                }
                $notesList[] = $srvText;
            }
            if (!empty($formattedAddress)) {
                $notesList[] = "Адрес: " . $formattedAddress;
            }
            if (!empty($startDatetime)) {
                $timeRange = $startDatetime;
                if (!empty($endDatetime)) {
                    $timeRange .= " — " . $endDatetime;
                }
                $notesList[] = "Время встречи: " . $timeRange;
            }
            if (!empty($bookDatetime)) {
                $notesList[] = "Дата записи: " . $bookDatetime;
            }
            if (!empty($providerFullName)) {
                $notesList[] = "Специалист: " . $providerFullName;
            }
            if (!empty($appointmentId)) {
                $notesList[] = "Appointment ID: #" . $appointmentId;
            }
            if (!empty($customerId)) {
                $notesList[] = "Customer ID: #" . $customerId;
            }
            if (!empty($aptNotes)) {
                $notesList[] = "Заметки встречи: " . $aptNotes;
            }
            if (!empty($custNotes)) {
                $notesList[] = "Заметки клиента: " . $custNotes;
            }
            if (!empty($rawNotes) && $rawNotes !== $aptNotes && $rawNotes !== $custNotes) {
                $notesList[] = $rawNotes;
            }
            $notes = implode("\n", $notesList);

            // 7. Resolve or Create Client (Deduplication: customer_id identity -> phone -> email)
            $isNewClient = false;
            $client = null;

            if ($customerId !== null) {
                $existingIdent = $this->identityMapper->findByChannelAndExternalId('easyappointments', (string)$customerId);
                if ($existingIdent !== null) {
                    try {
                        $client = $this->clientMapper->find($existingIdent->getClientId());
                    } catch (\Exception) {}
                }
            }

            if ($client === null) {
                $client = $this->clientMapper->findByPhoneOrEmail($phoneNormalized, $email);
            }

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
                // If incoming lead has real name, update client name if currently default or empty
                if (!empty($clientName) && ($client->getFullName() === 'Новый клиент' || empty($client->getFullName()))) {
                    $client->setFullName($clientName);
                }
                if (!empty($email)) {
                    $client->setEmail($email);
                }
                if (!empty($phoneNormalized)) {
                    $client->setPhone($phoneNormalized);
                }
                if (!empty($rawPhone)) {
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

            // Save EasyAppointments identity if provided
            if ($customerId !== null) {
                $existingIdent = $this->identityMapper->findByChannelAndExternalId('easyappointments', (string)$customerId);
                if ($existingIdent === null) {
                    $ident = new Identity();
                    $ident->setClientId((int)$client->getId());
                    $ident->setChannel('easyappointments');
                    $ident->setExternalId((string)$customerId);
                    $ident->setCreatedAt(new DateTime('now'));
                    $this->identityMapper->insert($ident);
                }
            }

            // 8. Prepare Activity
            $activityUuid = $this->generateUuid();

            // 9. Setup Folders and Public File Drop Link
            $providerFirst = $data['provider_first_name'] ?? 'contact';
            $safeCreateDt = str_replace([':', ' '], ['-', '_'], (string)($data['create_datetime'] ?? 'now'));
            $customActivityFolder = ($appointmentId !== null)
                ? sprintf('%s_%s_%s', $appointmentId, $providerFirst, $safeCreateDt)
                : $activityUuid;

            $folderInfo = $this->folderService->setupClientAndActivityFolder(
                $client->getFullName(),
                $client->getUuid(),
                $activityUuid,
                $responsibleUser,
                (int)$client->getId(),
                $customActivityFolder
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

            // 13. Sync Contact into Nextcloud Contacts module WITH ADDRESS, SERVICE & PROVIDER
            $contactInfo = null;
            try {
                $addressArray['customer_id'] = $customerId;
                $addressArray['customer_mini_crm_id'] = $client->getId();
                $addressArray['deck_task_id'] = $deckTaskId;
                $addressArray['folder_path'] = $folderInfo['folder_path'] ?? null;
                $addressArray['title'] = $serviceName ?: null;
                $addressArray['company'] = $providerFullName ?: null;

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

            // 15. Optional: Save Identity (Telegram / WhatsApp / Facebook)
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

            // 16. Initial System Message in Timeline
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

            // 17. Return comprehensive response preserving all incoming lead/booking fields
            $response = [
                'status' => 'success',
                'is_new_client' => $isNewClient,
                'customer_mini_crm_id' => (int)$client->getId(),

                // EasyAppointments booking & appointment fields
                'appointment_id' => $appointmentId !== null ? (int)$appointmentId : ($data['appointment_id'] ?? null),
                'create_datetime' => $data['create_datetime'] ?? null,
                'update_datetime' => $data['update_datetime'] ?? null,
                'book_datetime' => $bookDatetime,
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
                'location' => $data['location'] ?? null,
                'appointment_notes' => !empty($aptNotes) ? $aptNotes : ($data['appointment_notes'] ?? ''),
                'hash' => $data['hash'] ?? null,
                'color' => $data['color'] ?? null,
                'booking_status' => $data['status'] ?? 'Booked',
                'is_unavailability' => isset($data['is_unavailability']) ? (int)$data['is_unavailability'] : 0,

                // EasyAppointments customer fields
                'customer_id' => $customerId !== null ? (int)$customerId : ($data['customer_id'] ?? null),
                'customer_first_name' => $firstName ?: ($data['customer_first_name'] ?? ''),
                'customer_last_name' => $lastName ?: ($data['customer_last_name'] ?? ''),
                'customer_email' => $email ?: ($data['customer_email'] ?? ''),
                'customer_phone' => $rawPhone ?: ($data['customer_phone'] ?? ''),
                'customer_address' => $street ?: ($data['customer_address'] ?? ''),
                'customer_city' => $city ?: ($data['customer_city'] ?? ''),
                'customer_zip_code' => $postalCode ?: ($data['customer_zip_code'] ?? ''),

                // EasyAppointments provider & service fields
                'provider_first_name' => $providerFirst ?: ($data['provider_first_name'] ?? ''),
                'provider_last_name' => $providerLast ?: ($data['provider_last_name'] ?? ''),
                'service_name' => $serviceName ?: ($data['service_name'] ?? ''),
                'service_duration' => $serviceDuration,
                'service_price' => !empty($servicePrice) ? (string)$servicePrice : ($data['service_price'] ?? ''),

                // MiniCRM entities & Nextcloud artifacts
                'client' => $client->jsonSerialize(),
                'activity' => $activity->jsonSerialize(),
                'customer_file_path' => $folderInfo['folder_path'],
                'folder_path' => $folderInfo['activity_folder_path'],
                'file_drop_url' => $folderInfo['file_drop_url'],
                'customer_desk_id' => $deckTaskId ? '/apps/deck/#/card/' . $deckTaskId : null,
                'deck_task_id' => $deckTaskId,
                'calendar_event_id' => $calendarEventId,
                'contact' => $contactInfo,
            ];

            // Retain any other incoming fields passed in by caller
            foreach ($data as $key => $val) {
                if (!array_key_exists($key, $response)) {
                    $response[$key] = $val;
                }
            }

            return $response;
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
