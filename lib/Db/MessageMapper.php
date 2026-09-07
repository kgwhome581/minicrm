<?php

declare(strict_types=1);

namespace OCA\MiniCRM\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Message>
 */
class MessageMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'minicrm_messages', Message::class);
    }

    /**
     * Check if a message with this external ID already exists (idempotency/deduplication).
     */
    public function existsByExternalId(string $externalMessageId): bool {
        if (trim($externalMessageId) === '') {
            return false;
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select('id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('external_message_id', $qb->createNamedParameter($externalMessageId)))
            ->setMaxResults(1);

        $cursor = $qb->executeQuery();
        $row = $cursor->fetch();
        $cursor->closeCursor();

        return $row !== false;
    }

    /**
     * Get chronologically ordered timeline messages for a client.
     *
     * @return Message[]
     */
    public function getTimelineForClient(int $clientId, int $limit = 100, int $offset = 0): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('client_id', $qb->createNamedParameter($clientId, IQueryBuilder::PARAM_INT)))
            ->orderBy('id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return $this->findEntities($qb);
    }

    /**
     * Get messages for a specific activity.
     *
     * @return Message[]
     */
    public function findByActivityId(int $activityId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('activity_id', $qb->createNamedParameter($activityId, IQueryBuilder::PARAM_INT)))
            ->orderBy('id', 'ASC');

        return $this->findEntities($qb);
    }
}
