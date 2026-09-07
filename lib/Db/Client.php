<?php

declare(strict_types=1);

namespace OCA\MiniCRM\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getUuid()
 * @method void setUuid(string $uuid)
 * @method string getFullName()
 * @method void setFullName(string $fullName)
 * @method string getPhone()
 * @method void setPhone(string $phone)
 * @method string|null getPhoneRaw()
 * @method void setPhoneRaw(?string $phoneRaw)
 * @method string|null getEmail()
 * @method void setEmail(?string $email)
 * @method string|null getFolderPath()
 * @method void setFolderPath(?string $folderPath)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method DateTime|null getCreatedAt()
 * @method void setCreatedAt(?DateTime $createdAt)
 * @method DateTime|null getUpdatedAt()
 * @method void setUpdatedAt(?DateTime $updatedAt)
 */
class Client extends Entity implements JsonSerializable {
    protected string $uuid = '';
    protected string $fullName = '';
    protected string $phone = '';
    protected ?string $phoneRaw = null;
    protected ?string $email = null;
    protected ?string $folderPath = null;
    protected ?string $notes = null;
    protected ?DateTime $createdAt = null;
    protected ?DateTime $updatedAt = null;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'uuid' => $this->getUuid(),
            'full_name' => $this->getFullName(),
            'phone' => $this->getPhone(),
            'phone_raw' => $this->getPhoneRaw(),
            'email' => $this->getEmail(),
            'folder_path' => $this->getFolderPath(),
            'notes' => $this->getNotes(),
            'created_at' => $this->getCreatedAt()?->format(DateTime::ATOM),
            'updated_at' => $this->getUpdatedAt()?->format(DateTime::ATOM),
        ];
    }
}
