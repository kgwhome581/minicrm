<?php

declare(strict_types=1);

namespace OCA\MiniCRM\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getClientId()
 * @method void setClientId(int $clientId)
 * @method string getChannel()
 * @method void setChannel(string $channel)
 * @method string getExternalId()
 * @method void setExternalId(string $externalId)
 * @method DateTime|null getCreatedAt()
 * @method void setCreatedAt(?DateTime $createdAt)
 */
class Identity extends Entity implements JsonSerializable {
    protected int $clientId = 0;
    protected string $channel = '';
    protected string $externalId = '';
    protected ?DateTime $createdAt = null;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('clientId', 'integer');
        $this->addType('createdAt', 'datetime');
    }

    public function setClientId(int $clientId): void {
        $this->clientId = $clientId;
        $this->markFieldUpdated('clientId');
    }

    public function setChannel(string $channel): void {
        $this->channel = $channel;
        $this->markFieldUpdated('channel');
    }

    public function setExternalId(string $externalId): void {
        $this->externalId = $externalId;
        $this->markFieldUpdated('externalId');
    }

    public function setCreatedAt(?DateTime $createdAt): void {
        $this->createdAt = $createdAt;
        $this->markFieldUpdated('createdAt');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'client_id' => $this->getClientId(),
            'channel' => $this->getChannel(),
            'external_id' => $this->getExternalId(),
            'created_at' => $this->getCreatedAt()?->format(DateTime::ATOM),
        ];
    }
}
