<?php

declare(strict_types=1);

namespace OCA\MiniCRM\Service;

use DateTime;
use DateTimeZone;
use OCA\MiniCRM\Db\Client;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class ContactBridgeService {
    public function __construct(
        private IDBConnection $db,
        private LoggerInterface $logger
    ) {}

    /**
     * Create or update a CardDAV contact in Nextcloud Contacts.
     *
     * @param string $responsibleUser Target Nextcloud username (e.g. 'admin')
     * @param Client $client Client entity
     * @param string|null $folderUrl Web URL or path to client documents
     * @return array{success: bool, uid: string, uri: string, app_url: string}|null
     */
    public function syncContact(
        string $responsibleUser,
        Client $client,
        ?string $folderUrl = null,
        array $extra = []
    ): ?array {
        try {
            $addressbook = $this->getUserAddressBook($responsibleUser);
            if ($addressbook === null) {
                $this->logger->warning("No address book found for user {$responsibleUser}", ['app' => 'minicrm']);
                return null;
            }

            $addressbookId = (int)$addressbook['id'];
            $contactUid = $client->getUuid();
            if (empty($contactUid)) {
                $contactUid = $this->generateUuidV4();
            }

            $vcfFileName = $contactUid . '.vcf';

            // Format client name parts
            $fullName = trim($client->getFullName());
            $nameParts = preg_split('/\s+/', $fullName, 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';

            $phone = $client->getPhone() ?: $client->getPhoneRaw() ?: '';
            $email = $client->getEmail() ?: '';
            $notes = $client->getNotes() ?: '';
            $website = $folderUrl ?: '';

            // Address parts from extra or notes
            $street = trim((string)($extra['street'] ?? ''));
            $city = trim((string)($extra['city'] ?? ''));
            $state = trim((string)($extra['state'] ?? ''));
            $postalCode = trim((string)($extra['postal_code'] ?? ''));
            $country = trim((string)($extra['country'] ?? 'Canada'));
            $pobox = trim((string)($extra['po_box'] ?? ''));

            if (empty($street) && empty($city) && !empty($notes)) {
                if (preg_match('/Адрес:\s*(.*)$/mi', $notes, $addrMatch)) {
                    $rawAddr = trim($addrMatch[1]);
                    $addrParts = array_map('trim', explode(',', $rawAddr));
                    if (count($addrParts) >= 1) $street = $addrParts[0];
                    if (count($addrParts) >= 2) $city = $addrParts[1];
                    if (count($addrParts) >= 3) $state = $addrParts[2];
                    if (count($addrParts) >= 4) $postalCode = $addrParts[3];
                    if (count($addrParts) >= 5) $country = $addrParts[4];
                }
            }

            $customerId = $extra['customer_id'] ?? null;
            $crmId = $extra['customer_mini_crm_id'] ?? $client->getId();
            $deckId = $extra['deck_task_id'] ?? null;

            $metaLines = [];
            if (!empty($customerId)) {
                $metaLines[] = "ID EasyAppointments: #{$customerId}";
            }
            if (!empty($crmId)) {
                $metaLines[] = "ID MiniCRM: #{$crmId}";
            }
            if (!empty($website)) {
                $metaLines[] = "Папка файлов: {$website}";
            }
            if (!empty($deckId)) {
                $metaLines[] = "Deck: /apps/deck/#/card/{$deckId}";
            }

            if (!empty($metaLines)) {
                $headerBlock = implode("\n", $metaLines);
                if (empty($notes)) {
                    $notes = $headerBlock;
                } else if (!str_contains($notes, 'ID MiniCRM')) {
                    $notes = $headerBlock . "\n\n" . $notes;
                }
            }

            $nowUtc = (new DateTime('now', new DateTimeZone('UTC')))->format('Ymd\THis\Z');

            // Build standard RFC 6350 vCard 3.0
            $vcard = "BEGIN:VCARD\r\n" .
                "VERSION:3.0\r\n" .
                "PRODID:-//ViolaTax//MiniCRM Nextcloud//EN\r\n" .
                "UID:{$contactUid}\r\n" .
                "FN:{$this->escapeVcardString($fullName)}\r\n" .
                "N:{$this->escapeVcardString($lastName)};{$this->escapeVcardString($firstName)};;;\r\n";

            if (!empty($customerId)) {
                $vcard .= "X-EASYAPPOINTMENTS-ID:{$customerId}\r\n";
            }
            if (!empty($crmId)) {
                $vcard .= "X-MINICRM-ID:{$crmId}\r\n";
            }

            if (!empty($phone)) {
                $vcard .= "TEL;TYPE=CELL:{$this->escapeVcardString($phone)}\r\n";
            }
            if (!empty($email)) {
                $vcard .= "EMAIL;TYPE=OTHER:{$this->escapeVcardString($email)}\r\n";
            }
            if (!empty($street) || !empty($city) || !empty($postalCode)) {
                $cCountry = !empty($country) ? $country : 'Canada';
                $vcard .= "ADR;TYPE=HOME:{$this->escapeVcardString($pobox)};;{$this->escapeVcardString($street)};{$this->escapeVcardString($city)};{$this->escapeVcardString($state)};{$this->escapeVcardString($postalCode)};{$this->escapeVcardString($cCountry)}\r\n";
            }
            if (!empty($website)) {
                $vcard .= "URL;TYPE=WORK:{$this->escapeVcardString($website)}\r\n";
            }
            if (!empty($notes)) {
                $vcard .= "NOTE:{$this->escapeVcardString($notes)}\r\n";
            }
            $vcard .= "CATEGORIES:Clients\r\n" .
                "REV:{$nowUtc}\r\n" .
                "END:VCARD\r\n";

            $timeNow = time();
            $etag = '"' . md5($vcard) . '"';
            $size = strlen($vcard);

            // Check if card already exists
            $qb = $this->db->getQueryBuilder();
            $qb->select('id')
                ->from('cards')
                ->where($qb->expr()->eq('addressbookid', $qb->createNamedParameter($addressbookId)))
                ->andWhere($qb->expr()->eq('uri', $qb->createNamedParameter($vcfFileName)))
                ->setMaxResults(1);
            $res = $qb->executeQuery();
            $existingCard = $res->fetch();
            $res->closeCursor();

            if ($existingCard !== false && isset($existingCard['id'])) {
                $cardId = (int)$existingCard['id'];
                $upQb = $this->db->getQueryBuilder();
                $upQb->update('cards')
                    ->set('carddata', $upQb->createNamedParameter($vcard))
                    ->set('lastmodified', $upQb->createNamedParameter($timeNow))
                    ->set('etag', $upQb->createNamedParameter($etag))
                    ->set('size', $upQb->createNamedParameter($size))
                    ->where($upQb->expr()->eq('id', $upQb->createNamedParameter($cardId)))
                    ->executeStatement();
            } else {
                $insQb = $this->db->getQueryBuilder();
                $insQb->insert('cards')
                    ->values([
                        'addressbookid' => $insQb->createNamedParameter($addressbookId),
                        'carddata' => $insQb->createNamedParameter($vcard),
                        'uri' => $insQb->createNamedParameter($vcfFileName),
                        'lastmodified' => $insQb->createNamedParameter($timeNow),
                        'etag' => $insQb->createNamedParameter($etag),
                        'size' => $insQb->createNamedParameter($size),
                    ]);
                $insQb->executeStatement();
                $cardId = (int)$this->db->lastInsertId();
            }

            // Index search properties
            $this->updateCardProperties($addressbookId, $cardId, $fullName, $phone, $email, [
                'street' => $street,
                'city' => $city,
                'state' => $state,
                'postal_code' => $postalCode,
                'country' => $country,
                'po_box' => $pobox,
            ]);

            // Update synctoken for live sync
            $newSyncToken = ((int)($addressbook['synctoken'] ?? 0)) + 1;
            $syncQb = $this->db->getQueryBuilder();
            $syncQb->update('addressbooks')
                ->set('synctoken', $syncQb->createNamedParameter($newSyncToken))
                ->where($syncQb->expr()->eq('id', $syncQb->createNamedParameter($addressbookId)))
                ->executeStatement();

            $addressbookUri = (string)($addressbook['uri'] ?? 'contacts');
            $encodedContactId = base64_encode($contactUid . '~' . $addressbookUri);
            $appUrl = '/apps/contacts/All%20contacts/' . $encodedContactId;

            return [
                'success' => true,
                'uid' => $contactUid,
                'uri' => $vcfFileName,
                'addressbook' => $addressbookUri,
                'app_url' => $appUrl,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error syncing contact to Nextcloud Contacts: ' . $e->getMessage(), ['app' => 'minicrm']);
            return null;
        }
    }

    /**
     * Check if a contact card exists in Nextcloud Contacts, and parse its details (address, etc.).
     *
     * @return array{exists: bool, app_url: string|null, address: string|null, phone: string|null, email: string|null, notes: string|null}
     */
    public function getContactInfo(
        string $clientUuid,
        ?string $email = null,
        ?string $phone = null,
        ?string $fullName = null,
        ?string $fallbackNotes = null
    ): array {
        $fallbackAddress = null;
        if (!empty($fallbackNotes)) {
            if (preg_match('/(?:Адрес|Address):\s*([^\r\n]+)/iu', $fallbackNotes, $m)) {
                $fallbackAddress = trim($m[1]);
            }
        }

        try {
            $card = null;

            // 1. Try by UUID filename
            if (!empty($clientUuid)) {
                $vcfFileName = $clientUuid . '.vcf';
                $qb = $this->db->getQueryBuilder();
                $qb->select('*')
                    ->from('cards')
                    ->where($qb->expr()->eq('uri', $qb->createNamedParameter($vcfFileName)))
                    ->setMaxResults(1);
                $res = $qb->executeQuery();
                $found = $res->fetch();
                $res->closeCursor();
                if ($found !== false && !empty($found['uri'])) {
                    $card = $found;
                }
            }

            // 2. Try by email via cards_properties
            if ($card === null && !empty($email)) {
                $card = $this->findCardByProperty('EMAIL', $email, false);
            }

            // 3. Try by phone via cards_properties
            if ($card === null && !empty($phone)) {
                $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
                if (strlen($cleanPhone) >= 5) {
                    $card = $this->findCardByProperty('TEL', '%' . substr($cleanPhone, -7) . '%', true);
                }
            }

            // 4. Try by full name via cards_properties
            if ($card === null && !empty($fullName)) {
                $card = $this->findCardByProperty('FN', trim($fullName), false);
            }

            if ($card !== null && !empty($card['uri'])) {
                $addressbookUri = 'contacts';
                if (!empty($card['addressbookid'])) {
                    try {
                        $abQb = $this->db->getQueryBuilder();
                        $abQb->select('uri')
                            ->from('addressbooks')
                            ->where($abQb->expr()->eq('id', $abQb->createNamedParameter((int)$card['addressbookid'])))
                            ->setMaxResults(1);
                        $abRes = $abQb->executeQuery();
                        $abRow = $abRes->fetch();
                        $abRes->closeCursor();
                        if ($abRow !== false && !empty($abRow['uri'])) {
                            $addressbookUri = (string)$abRow['uri'];
                        }
                    } catch (\Throwable) {}
                }

                $cardData = (string)($card['carddata'] ?? '');
                $rawUri = (string)$card['uri'];
                $contactUid = preg_replace('/\.vcf$/i', '', $rawUri);
                if (preg_match('/^UID:(.+)$/mi', $cardData, $uidMatch)) {
                    $contactUid = trim($uidMatch[1]);
                }

                $encodedContactId = base64_encode($contactUid . '~' . $addressbookUri);
                $appUrl = '/apps/contacts/All%20contacts/' . $encodedContactId;
                $parsed = $this->parseVcardData($cardData);

                return [
                    'exists' => true,
                    'app_url' => $appUrl,
                    'uid' => $contactUid,
                    'addressbook' => $addressbookUri,
                    'address' => $parsed['address'] ?: $fallbackAddress,
                    'email' => $parsed['email'] ?: $email,
                    'phone' => $parsed['phone'] ?: $phone,
                    'notes' => $parsed['notes'] ?: $fallbackNotes,
                ];
            }
        } catch (\Throwable $e) {
            $this->logger->debug('Error checking contact card: ' . $e->getMessage(), ['app' => 'minicrm']);
        }

        return [
            'exists' => false,
            'app_url' => null,
            'address' => $fallbackAddress,
            'email' => $email,
            'phone' => $phone,
            'notes' => $fallbackNotes,
        ];
    }

    /**
     * Looks up card by property in cards_properties table.
     */
    private function findCardByProperty(string $name, string $value, bool $like = false): ?array {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('cardid')
                ->from('cards_properties')
                ->where($qb->expr()->eq('name', $qb->createNamedParameter($name)));

            if ($like) {
                $qb->andWhere($qb->expr()->like('value', $qb->createNamedParameter($value)));
            } else {
                $qb->andWhere($qb->expr()->eq('value', $qb->createNamedParameter($value)));
            }

            $qb->setMaxResults(1);
            $res = $qb->executeQuery();
            $row = $res->fetch();
            $res->closeCursor();

            if ($row !== false && isset($row['cardid']) && !empty($row['cardid'])) {
                $cardId = (int)$row['cardid'];
                $qb2 = $this->db->getQueryBuilder();
                $qb2->select('*')
                    ->from('cards')
                    ->where($qb2->expr()->eq('id', $qb2->createNamedParameter($cardId)))
                    ->setMaxResults(1);
                $res2 = $qb2->executeQuery();
                $card = $res2->fetch();
                $res2->closeCursor();

                if ($card !== false && !empty($card['uri'])) {
                    return $card;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->debug('Error looking up card by property: ' . $e->getMessage(), ['app' => 'minicrm']);
        }

        return null;
    }

    /**
     * Parses key fields (Address, Notes, etc.) from raw vCard data.
     *
     * @return array{address: string|null, email: string|null, phone: string|null, notes: string|null}
     */
    private function parseVcardData(string $cardData): array {
        $result = [
            'address' => null,
            'email' => null,
            'phone' => null,
            'notes' => null,
        ];

        if (empty($cardData)) {
            return $result;
        }

        // Unfold wrapped lines
        $unfolded = preg_replace("/\r\n[ \t]/", '', $cardData);
        $unfolded = preg_replace("/\n[ \t]/", '', (string)$unfolded);

        // 1. ADR field: POBox;Extended;Street;City;State;PostalCode;Country
        if (preg_match('/^ADR[^:]*:(.*)$/mi', (string)$unfolded, $adrMatch)) {
            $parts = explode(';', trim($adrMatch[1]) . ';;;;;;');
            $pobox = trim(str_replace(['\;', '\,'], [';', ','], $parts[0] ?? ''));
            $extended = trim(str_replace(['\;', '\,'], [';', ','], $parts[1] ?? ''));
            $street = trim(str_replace(['\;', '\,'], [';', ','], $parts[2] ?? ''));
            $city = trim(str_replace(['\;', '\,'], [';', ','], $parts[3] ?? ''));
            $state = trim(str_replace(['\;', '\,'], [';', ','], $parts[4] ?? ''));
            $postalCode = trim(str_replace(['\;', '\,'], [';', ','], $parts[5] ?? ''));
            $country = trim(str_replace(['\;', '\,'], [';', ','], $parts[6] ?? ''));

            $addrParts = [];
            if (!empty($pobox)) {
                $addrParts[] = $pobox;
            }
            if (!empty($street)) {
                $addrParts[] = $street;
            }
            if (!empty($extended)) {
                $addrParts[] = $extended;
            }
            if (!empty($city)) {
                $addrParts[] = $city;
            }
            if (!empty($state)) {
                $addrParts[] = $state;
            }
            if (!empty($postalCode)) {
                $addrParts[] = $postalCode;
            }
            if (!empty($country)) {
                $addrParts[] = $country;
            }

            if (!empty($addrParts)) {
                $result['address'] = implode(', ', $addrParts);
            }
        }

        // 2. NOTE field
        if (preg_match('/^NOTE[^:]*:(.*)$/mi', (string)$unfolded, $noteMatch)) {
            $note = trim($noteMatch[1]);
            $note = str_replace(['\n', '\N', '\;', '\,'], ["\n", "\n", ';', ','], $note);
            if (!empty($note)) {
                $result['notes'] = $note;
            }
        }

        // 3. EMAIL field
        if (preg_match('/^EMAIL[^:]*:(.*)$/mi', (string)$unfolded, $emailMatch)) {
            $cleanEmail = trim(str_replace(['\;', '\,'], [';', ','], $emailMatch[1]));
            if (!empty($cleanEmail)) {
                $result['email'] = $cleanEmail;
            }
        }

        // 4. TEL field
        if (preg_match('/^TEL[^:]*:(.*)$/mi', (string)$unfolded, $telMatch)) {
            $cleanTel = trim(str_replace(['\;', '\,'], [';', ','], $telMatch[1]));
            if (!empty($cleanTel)) {
                $result['phone'] = $cleanTel;
            }
        }

        return $result;
    }

    /**
     * Finds primary user addressbook.
     */
    private function getUserAddressBook(string $username): ?array {
        $principalUri = 'principals/users/' . $username;

        $qb = $this->db->getQueryBuilder();
        $qb->select('id', 'uri', 'displayname', 'synctoken')
            ->from('addressbooks')
            ->where($qb->expr()->eq('principaluri', $qb->createNamedParameter($principalUri)))
            ->orderBy('id', 'ASC');

        $result = $qb->executeQuery();
        $rows = $result->fetchAll();
        $result->closeCursor();

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $uriLower = strtolower((string)($row['uri'] ?? ''));
                $nameLower = strtolower((string)($row['displayname'] ?? ''));
                if ($uriLower === 'contacts' || $nameLower === 'contacts') {
                    return $row;
                }
            }
            return $rows[0];
        }

        // Fallback: search for any user address book
        $qb2 = $this->db->getQueryBuilder();
        $qb2->select('id', 'uri', 'displayname', 'synctoken')
            ->from('addressbooks')
            ->orderBy('id', 'ASC')
            ->setMaxResults(1);
        $res2 = $qb2->executeQuery();
        $row2 = $res2->fetch();
        $res2->closeCursor();

        return ($row2 !== false && isset($row2['id'])) ? $row2 : null;
    }

    private function updateCardProperties(
        int $addressbookId,
        int $cardId,
        string $fullName,
        string $phone,
        string $email,
        array $address = []
    ): void {
        try {
            $delQb = $this->db->getQueryBuilder();
            $delQb->delete('cards_properties')
                ->where($delQb->expr()->eq('cardid', $delQb->createNamedParameter($cardId)))
                ->executeStatement();

            $props = [
                ['FN', $fullName],
                ['CATEGORIES', 'Clients'],
            ];
            if (!empty($phone)) {
                $props[] = ['TEL', $phone];
            }
            if (!empty($email)) {
                $props[] = ['EMAIL', $email];
            }
            if (!empty($address['street']) || !empty($address['city']) || !empty($address['postal_code'])) {
                $pobox = $address['po_box'] ?? '';
                $street = $address['street'] ?? '';
                $city = $address['city'] ?? '';
                $state = $address['state'] ?? '';
                $postalCode = $address['postal_code'] ?? '';
                $country = $address['country'] ?? 'Canada';
                $adrValue = "{$pobox};;{$street};{$city};{$state};{$postalCode};{$country}";
                $props[] = ['ADR', $adrValue];
            }

            foreach ($props as [$name, $val]) {
                $ins = $this->db->getQueryBuilder();
                $ins->insert('cards_properties')
                    ->values([
                        'addressbookid' => $ins->createNamedParameter($addressbookId),
                        'cardid' => $ins->createNamedParameter($cardId),
                        'name' => $ins->createNamedParameter($name),
                        'value' => $ins->createNamedParameter($val),
                        'preferred' => $ins->createNamedParameter(0),
                    ])
                    ->executeStatement();
            }
        } catch (\Throwable $e) {
            $this->logger->debug('Card properties indexing skipped: ' . $e->getMessage(), ['app' => 'minicrm']);
        }
    }

    private function escapeVcardString(string $text): string {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace("\r\n", '\n', $text);
        $text = str_replace("\n", '\n', $text);
        return $text;
    }

    private function generateUuidV4(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
