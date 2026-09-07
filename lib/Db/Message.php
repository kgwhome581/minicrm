<?php

declare(strict_types=1);

namespace OCA\MiniCRM\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getClientId()
 * @method void setClientId(int $clientId)
 * @method int|null getActivityId()
 * @method void setActivityId(?int $activityId)
 * @method string getChannel()
 * @method void setChannel(string $channel)
 * @method string getDirection()
 * @method void setDirection(string $direction)
 * @method string|null getSenderRecipient()
 * @method void setSenderRecipient(?string $senderRecipient)
 * @method string|null getSubject()
 * @method void setSubject(?string $subject)
 * @method string|null getContent()
 * @method void setContent(?string $content)
 * @method string|null getAttachments()
 * @method void setAttachments(?string $attachments)
 * @method string|null getExternalMessageId()
 * @method void setExternalMessageId(?string $externalMessageId)
 * @method DateTime|null getCreatedAt()
 * @method void setCreatedAt(?DateTime $createdAt)
 */
class Message extends Entity implements JsonSerializable {
    protected int $clientId = 0;
    protected ?int $activityId = null;
    protected string $channel = 'email'; // email, telegram, whatsapp, facebook
    protected string $direction = 'inbound'; // inbound, outbound
    protected ?string $senderRecipient = null;
    protected ?string $subject = null;
    protected ?string $content = null;
    protected ?string $attachments = null;
    protected ?string $externalMessageId = null;
    protected ?DateTime $createdAt = null;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('clientId', 'integer');
        $this->addType('activityId', 'integer');
        $this->addType('channel', 'string');
        $this->addType('direction', 'string');
        $this->addType('createdAt', 'datetime');
    }

    public function setClientId(int $clientId): void {
        $this->clientId = $clientId;
        $this->markFieldUpdated('clientId');
    }

    public function setActivityId(?int $activityId): void {
        $this->activityId = $activityId;
        $this->markFieldUpdated('activityId');
    }

    public function setChannel(string $channel): void {
        $this->channel = $channel;
        $this->markFieldUpdated('channel');
    }

    public function setDirection(string $direction): void {
        $this->direction = $direction;
        $this->markFieldUpdated('direction');
    }

    public function setSenderRecipient(?string $senderRecipient): void {
        $this->senderRecipient = $senderRecipient;
        $this->markFieldUpdated('senderRecipient');
    }

    public function setSubject(?string $subject): void {
        $this->subject = $subject;
        $this->markFieldUpdated('subject');
    }

    public function setContent(?string $content): void {
        $this->content = $content;
        $this->markFieldUpdated('content');
    }

    public function setExternalMessageId(?string $externalMessageId): void {
        $this->externalMessageId = $externalMessageId;
        $this->markFieldUpdated('externalMessageId');
    }

    public function setCreatedAt(?DateTime $createdAt): void {
        $this->createdAt = $createdAt;
        $this->markFieldUpdated('createdAt');
    }

    public function getAttachmentsList(): array {
        if (empty($this->attachments)) {
            return [];
        }
        $decoded = json_decode($this->attachments, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function setAttachmentsList(array $attachments): void {
        $this->setAttachments(json_encode($attachments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'client_id' => $this->getClientId(),
            'activity_id' => $this->getActivityId(),
            'channel' => $this->getChannel(),
            'direction' => $this->getDirection(),
            'sender_recipient' => $this->getSenderRecipient(),
            'subject' => $this->getSubject(),
            'content' => $this->getContent(),
            'attachments' => $this->getAttachmentsList(),
            'external_message_id' => $this->getExternalMessageId(),
            'created_at' => $this->getCreatedAt()?->format(DateTime::ATOM),
        ];
    }
}
