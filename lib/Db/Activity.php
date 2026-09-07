<?php

declare(strict_types=1);

namespace OCA\MiniCRM\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getActivityUuid()
 * @method void setActivityUuid(string $activityUuid)
 * @method int getClientId()
 * @method void setClientId(int $clientId)
 * @method string getSource()
 * @method void setSource(string $source)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getResponsibleUser()
 * @method void setResponsibleUser(?string $responsibleUser)
 * @method int|null getDeckTaskId()
 * @method void setDeckTaskId(?int $deckTaskId)
 * @method string|null getCalendarEventId()
 * @method void setCalendarEventId(?string $calendarEventId)
 * @method DateTime|null getMeetingTime()
 * @method void setMeetingTime(?DateTime $meetingTime)
 * @method string|null getFileDropUrl()
 * @method void setFileDropUrl(?string $fileDropUrl)
 * @method DateTime|null getCreatedAt()
 * @method void setCreatedAt(?DateTime $createdAt)
 * @method DateTime|null getUpdatedAt()
 * @method void setUpdatedAt(?DateTime $updatedAt)
 */
class Activity extends Entity implements JsonSerializable {
    protected string $activityUuid = '';
    protected int $clientId = 0;
    protected string $source = 'easypoint';
    protected string $status = 'scheduled';
    protected ?string $responsibleUser = null;
    protected ?int $deckTaskId = null;
    protected ?string $calendarEventId = null;
    protected ?DateTime $meetingTime = null;
    protected ?string $fileDropUrl = null;
    protected ?DateTime $createdAt = null;
    protected ?DateTime $updatedAt = null;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('clientId', 'integer');
        $this->addType('deckTaskId', 'integer');
        $this->addType('meetingTime', 'datetime');
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'activity_uuid' => $this->getActivityUuid(),
            'client_id' => $this->getClientId(),
            'source' => $this->getSource(),
            'status' => $this->getStatus(),
            'responsible_user' => $this->getResponsibleUser(),
            'deck_task_id' => $this->getDeckTaskId(),
            'calendar_event_id' => $this->getCalendarEventId(),
            'meeting_time' => $this->getMeetingTime()?->format(DateTime::ATOM),
            'file_drop_url' => $this->getFileDropUrl(),
            'created_at' => $this->getCreatedAt()?->format(DateTime::ATOM),
            'updated_at' => $this->getUpdatedAt()?->format(DateTime::ATOM),
        ];
    }
}
