<?php

declare(strict_types=1);

namespace OCA\MiniCRM\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Activity>
 */
class ActivityMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'minicrm_activities', Activity::class);
    }

    /**
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     */
    public function find(int $id): Activity {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

        return $this->findEntity($qb);
    }

    /**
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     */
    public function findByActivityUuid(string $activityUuid): Activity {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('activity_uuid', $qb->createNamedParameter($activityUuid)));

        return $this->findEntity($qb);
    }

    /**
     * Finds all activities for a client ordered by latest first.
     *
     * @return Activity[]
     */
    public function findByClientId(int $clientId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('client_id', $qb->createNamedParameter($clientId, IQueryBuilder::PARAM_INT)))
            ->orderBy('id', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * Finds latest active or scheduled activity for client.
     */
    public function findLatestForClient(int $clientId): ?Activity {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('client_id', $qb->createNamedParameter($clientId, IQueryBuilder::PARAM_INT)))
            ->orderBy('id', 'DESC')
            ->setMaxResults(1);

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException) {
            return null;
        }
    }
}
