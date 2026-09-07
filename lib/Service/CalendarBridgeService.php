<?php

declare(strict_types=1);

namespace OCA\MiniCRM\Service;

use DateTime;
use DateTimeZone;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class CalendarBridgeService {
    public function __construct(
        private IDBConnection $db,
        private LoggerInterface $logger
    ) {}

    /**
     * Create a Calendar event for the meeting in the user's primary calendar.
     *
     * @return string|null Returns the UID of the created calendar event or null on failure.
     */
    public function createEvent(
        string $responsibleUser,
        string $summary,
        DateTime $meetingStart,
        int $durationMinutes = 60,
        string $description = '',
        string $location = ''
    ): ?string {
        try {
            $calendarId = $this->getUserPrimaryCalendarId($responsibleUser);
            if ($calendarId === null) {
                $this->logger->warning("No calendar found for user {$responsibleUser}", ['app' => 'minicrm']);
                return null;
            }

            $meetingEnd = (clone $meetingStart)->modify("+{$durationMinutes} minutes");
            $eventUid = $this->generateUuidV4();
            $icsFileName = $eventUid . '.ics';

            // Format dates in UTC for RFC 5545 compliance
            $utcZone = new DateTimeZone('UTC');
            $dtStartUtc = (clone $meetingStart)->setTimezone($utcZone)->format('Ymd\THis\Z');
            $dtEndUtc = (clone $meetingEnd)->setTimezone($utcZone)->format('Ymd\THis\Z');
            $dtStamp = (new DateTime('now', $utcZone))->format('Ymd\THis\Z');

            $escapedSummary = $this->escapeIcsString($summary);
            $escapedDesc = $this->escapeIcsString($description);
            $escapedLocation = $this->escapeIcsString($location);

            $icsData = "BEGIN:VCALENDAR\r\n" .
                "VERSION:2.0\r\n" .
                "PRODID:-//ViolaTax//MiniCRM Nextcloud//EN\r\n" .
                "CALSCALE:GREGORIAN\r\n" .
                "BEGIN:VEVENT\r\n" .
                "UID:{$eventUid}\r\n" .
                "DTSTAMP:{$dtStamp}\r\n" .
                "DTSTART:{$dtStartUtc}\r\n" .
                "DTEND:{$dtEndUtc}\r\n" .
                "SUMMARY:{$escapedSummary}\r\n" .
                "DESCRIPTION:{$escapedDesc}\r\n" .
                "LOCATION:{$escapedLocation}\r\n" .
                "STATUS:CONFIRMED\r\n" .
                "END:VEVENT\r\n" .
                "END:VCALENDAR\r\n";

            $qb = $this->db->getQueryBuilder();
            $qb->insert('calendarobjects')
                ->values([
                    'calendarid' => $qb->createNamedParameter($calendarId),
                    'uri' => $qb->createNamedParameter($icsFileName),
                    'calendardata' => $qb->createNamedParameter($icsData),
                    'lastmodified' => $qb->createNamedParameter(time()),
                    'componenttype' => $qb->createNamedParameter('VEVENT'),
                    'firstoccurence' => $qb->createNamedParameter($meetingStart->getTimestamp()),
                    'lastoccurence' => $qb->createNamedParameter($meetingEnd->getTimestamp()),
                    'uid' => $qb->createNamedParameter($eventUid),
                    'classification' => $qb->createNamedParameter(0),
                ]);

            $qb->executeStatement();

            return $eventUid;
        } catch (\Exception $e) {
            $this->logger->error('Error creating calendar event: ' . $e->getMessage(), ['app' => 'minicrm']);
            return null;
        }
    }

    /**
     * Finds the primary calendar ID for a user.
     */
    private function getUserPrimaryCalendarId(string $username): ?int {
        $principalUri = 'principals/users/' . $username;

        $qb = $this->db->getQueryBuilder();
        $qb->select('id')
            ->from('calendars')
            ->where($qb->expr()->eq('principaluri', $qb->createNamedParameter($principalUri)))
            ->orderBy('id', 'ASC')
            ->setMaxResults(1);

        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        if ($row !== false && isset($row['id'])) {
            return (int)$row['id'];
        }

        // Fallback: search for any calendar
        $qb2 = $this->db->getQueryBuilder();
        $qb2->select('id')
            ->from('calendars')
            ->orderBy('id', 'ASC')
            ->setMaxResults(1);
        $res2 = $qb2->executeQuery();
        $row2 = $res2->fetch();
        $res2->closeCursor();

        return ($row2 !== false && isset($row2['id'])) ? (int)$row2['id'] : null;
    }

    private function escapeIcsString(string $text): string {
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
