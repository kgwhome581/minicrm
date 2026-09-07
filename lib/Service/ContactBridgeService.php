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
        ?string $folderUrl = null
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

            $nowUtc = (new DateTime('now', new DateTimeZone('UTC')))->format('Ymd\THis\Z');

            // Build standard RFC 6350 vCard 3.0
            $vcard = "BEGIN:VCARD\r\n" .
                "VERSION:3.0\r\n" .
                "PRODID:-//ViolaTax//MiniCRM Nextcloud//EN\r\n" .
                "UID:{$contactUid}\r\n" .
                "FN:{$this->escapeVcardString($fullName)}\r\n" .
                "N:{$this->escapeVcardString($lastName)};{$this->escapeVcardString($firstName)};;;\r\n";

            if (!empty($phone)) {
                $vcard .= "TEL;TYPE=CELL:{$this->escapeVcardString($phone)}\r\n";
            }
            if (!empty($email)) {
                $vcard .= "EMAIL;TYPE=OTHER:{$this->escapeVcardString($email)}\r\n";
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
            $this->updateCardProperties($addressbookId, $cardId, $fullName, $phone, $email);

            // Update synctoken for live sync
            $newSyncToken = ((int)($addressbook['synctoken'] ?? 0)) + 1;
            $syncQb = $this->db->getQueryBuilder();
            $syncQb->update('addressbooks')
                ->set('synctoken', $syncQb->createNamedParameter($newSyncToken))
                ->where($syncQb->expr()->eq('id', $syncQb->createNamedParameter($addressbookId)))
                ->executeStatement();

            $encodedUri = rtrim(strtr(base64_encode($vcfFileName), '+/', '-_'), '=');
            $appUrl = '/apps/contacts/All%20contacts/' . $encodedUri;

            return [
                'success' => true,
                'uid' => $contactUid,
                'uri' => $vcfFileName,
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
        ?string $fullName = null
    ): array {
        try {
            $card = null;

            // 1. Try by UUID filename
            if (!empty($clientUuid)) {
                $vcfFileName = $clientUuid . '.vcf';
                $qb = $this->db->getQueryBuilder();
                $qb->select('id', 'uri', 'carddata')
                    ->from('cards')
                    ->where($qb->expr()->eq('uri', $qb->createNamedParameter($vcfFileName)))
                    ->setMaxResults(1);
                $res = $qb->executeQuery();
                $card = $res->fetch();
                $res->closeCursor();
            }

            // 2. Try by email via cards_properties
            if (($card === false || $card === null) && !empty($email)) {
                $qb = $this->db->getQueryBuilder();
                $qb->select('c.id', 'c.uri', 'c.carddata')
                    ->from('cards_properties', 'cp')
                    ->innerJoin('cp', 'cards', 'c', $qb->expr()->eq('cp.cardid', 'c.id'))
                    ->where($qb->expr()->eq('cp.name', $qb->createNamedParameter('EMAIL')))
                    ->andWhere($qb->expr()->eq('cp.value', $qb->createNamedParameter($email)))
                    ->setMaxResults(1);
                $res = $qb->executeQuery();
                $card = $res->fetch();
                $res->closeCursor();
            }

            // 3. Try by phone via cards_properties
            if (($card === false || $card === null) && !empty($phone)) {
                $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
                if (strlen($cleanPhone) >= 5) {
                    $qb = $this->db->getQueryBuilder();
                    $qb->select('c.id', 'c.uri', 'c.carddata')
                        ->from('cards_properties', 'cp')
                        ->innerJoin('cp', 'cards', 'c', $qb->expr()->eq('cp.cardid', 'c.id'))
                        ->where($qb->expr()->eq('cp.name', $qb->createNamedParameter('TEL')))
                        ->andWhere($qb->expr()->like('cp.value', $qb->createNamedParameter('%' . substr($cleanPhone, -7) . '%')))
                        ->setMaxResults(1);
                    $res = $qb->executeQuery();
                    $card = $res->fetch();
                    $res->closeCursor();
                }
            }

            // 4. Try by full name via cards_properties
            if (($card === false || $card === null) && !empty($fullName)) {
                $qb = $this->db->getQueryBuilder();
                $qb->select('c.id', 'c.uri', 'c.carddata')
                    ->from('cards_properties', 'cp')
                    ->innerJoin('cp', 'cards', 'c', $qb->expr()->eq('cp.cardid', 'c.id'))
                    ->where($qb->expr()->eq('cp.name', $qb->createNamedParameter('FN')))
                    ->andWhere($qb->expr()->eq('cp.value', $qb->createNamedParameter(trim($fullName))))
                    ->setMaxResults(1);
                $res = $qb->executeQuery();
                $card = $res->fetch();
                $res->closeCursor();
            }

            if ($card !== false && $card !== null && !empty($card['uri'])) {
                $encodedUri = rtrim(strtr(base64_encode((string)$card['uri']), '+/', '-_'), '=');
                $cardData = (string)($card['carddata'] ?? '');
                $parsed = $this->parseVcardData($cardData);

                return [
                    'exists' => true,
                    'app_url' => '/apps/contacts/All%20contacts/' . $encodedUri,
                    'address' => $parsed['address'] ?? null,
                    'email' => $parsed['email'] ?? $email,
                    'phone' => $parsed['phone'] ?? $phone,
                    'notes' => $parsed['notes'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            $this->logger->debug('Error checking contact card: ' . $e->getMessage(), ['app' => 'minicrm']);
        }

        return [
            'exists' => false,
            'app_url' => null,
            'address' => null,
            'email' => $email,
            'phone' => $phone,
            'notes' => null,
        ];
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

    private function updateCardProperties(int $addressbookId, int $cardId, string $fullName, string $phone, string $email): void {
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
        } catch (\Exception $e) {
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
