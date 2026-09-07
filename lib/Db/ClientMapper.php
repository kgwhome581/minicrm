<?php

declare(strict_types=1);

namespace OCA\MiniCRM\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Client>
 */
class ClientMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'minicrm_clients', Client::class);
    }

    /**
     * Finds a client by primary ID.
     *
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     */
    public function find(int $id): Client {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

        return $this->findEntity($qb);
    }

    /**
     * Finds a client by UUID.
     *
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     */
    public function findByUuid(string $uuid): Client {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('uuid', $qb->createNamedParameter($uuid)));

        return $this->findEntity($qb);
    }

    /**
     * Finds a client by normalized phone number or email.
     *
     * @return Client|null
     */
    public function findByPhoneOrEmail(?string $phone, ?string $email): ?Client {
        if (empty($phone) && empty($email)) {
            return null;
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName());

        if (!empty($phone) && !empty($email)) {
            $qb->where(
                $qb->expr()->orX(
                    $qb->expr()->eq('phone', $qb->createNamedParameter($phone)),
                    $qb->expr()->eq('email', $qb->createNamedParameter(strtolower($email)))
                )
            );
        } elseif (!empty($phone)) {
            $qb->where($qb->expr()->eq('phone', $qb->createNamedParameter($phone)));
        } else {
            $qb->where($qb->expr()->eq('email', $qb->createNamedParameter(strtolower((string)$email))));
        }

        $qb->setMaxResults(1);

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException) {
            return null;
        }
    }

    /**
     * Search clients by name, phone or email.
     *
     * @return Client[]
     */
    public function search(string $query, int $limit = 50, int $offset = 0): array {
        $qb = $this->db->getQueryBuilder();
        $term = '%' . $this->db->escapeLikeParameter($query) . '%';

        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->orX(
                    $qb->expr()->ilike('full_name', $qb->createNamedParameter($term)),
                    $qb->expr()->ilike('phone', $qb->createNamedParameter($term)),
                    $qb->expr()->ilike('email', $qb->createNamedParameter($term))
                )
            )
            ->orderBy('id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return $this->findEntities($qb);
    }
}
