<?php

declare(strict_types=1);

namespace OCA\MiniCRM\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Identity>
 */
class IdentityMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'minicrm_client_identities', Identity::class);
    }

    /**
     * Find identity by channel and external ID (e.g. telegram and chat_id).
     */
    public function findByChannelAndExternalId(string $channel, string $externalId): ?Identity {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->eq('channel', $qb->createNamedParameter($channel)),
                    $qb->expr()->eq('external_id', $qb->createNamedParameter($externalId))
                )
            )
            ->setMaxResults(1);

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException) {
            return null;
        }
    }

    /**
     * Finds all linked identities for a client.
     *
     * @return Identity[]
     */
    public function findByClientId(int $clientId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('client_id', $qb->createNamedParameter($clientId)));

        return $this->findEntities($qb);
    }
}
