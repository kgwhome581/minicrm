<?php

declare(strict_types=1);

namespace OCA\MiniCRM\Service;

use DateTime;
use OCP\IConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class DeckBridgeService {
    public function __construct(
        private IDBConnection $db,
        private IConfig $config,
        private LoggerInterface $logger
    ) {}

    /**
     * Creates a card in Nextcloud Deck.
     *
     * @return int|null Created Deck card ID or null on failure.
     */
    public function createCard(
        string $title,
        string $description,
        ?string $responsibleUser = null,
        ?DateTime $dueDate = null,
        ?int $targetBoardId = null,
        ?int $targetStackId = null
    ): ?int {
        try {
            // 1. Resolve stack ID
            $stackId = $targetStackId ?? $this->resolveDefaultStackId($targetBoardId);
            if ($stackId === null) {
                $this->logger->warning('No suitable Deck stack/board found to create card', ['app' => 'minicrm']);
                return null;
            }

            $now = time();
            $dueFormatted = $dueDate?->format('Y-m-d H:i:s');
            $owner = $responsibleUser ?? 'admin';

            // 2. Insert into oc_deck_cards
            $qb = $this->db->getQueryBuilder();
            $qb->insert('deck_cards')
                ->values([
                    'title' => $qb->createNamedParameter($title),
                    'description' => $qb->createNamedParameter($description),
                    'stack_id' => $qb->createNamedParameter($stackId),
                    'type' => $qb->createNamedParameter('plain'),
                    'last_modified' => $qb->createNamedParameter($now),
                    'created_at' => $qb->createNamedParameter($now),
                    'order' => $qb->createNamedParameter(0),
                    'owner' => $qb->createNamedParameter($owner),
                    'archived' => $qb->createNamedParameter(0),
                ]);

            if ($dueFormatted !== null) {
                $qb->setValue('duedate', $qb->createNamedParameter($dueFormatted));
            }

            $qb->executeStatement();
            $cardId = (int)$this->db->lastInsertId('deck_cards');

            // 3. Assign responsible user to the card if specified
            if (!empty($responsibleUser)) {
                $this->assignUserToCard($cardId, $responsibleUser);
            }

            return $cardId;
        } catch (\Exception $e) {
            $this->logger->error('Error creating Deck card: ' . $e->getMessage(), ['app' => 'minicrm']);
            return null;
        }
    }

    /**
     * Resolves default stack ID, searching configured values or the first active board's first column.
     */
    private function resolveDefaultStackId(?int $boardId): ?int {
        // Check config first
        $configuredStackId = (int)$this->config->getAppValue('minicrm', 'default_deck_stack_id', '0');
        if ($configuredStackId > 0) {
            return $configuredStackId;
        }

        $qb = $this->db->getQueryBuilder();

        if ($boardId !== null && $boardId > 0) {
            $qb->select('id')
                ->from('deck_stacks')
                ->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId)))
                ->orderBy('order', 'ASC')
                ->setMaxResults(1);
        } else {
            // Find first non-archived board's first stack
            $qb->select('s.id')
                ->from('deck_stacks', 's')
                ->innerJoin('s', 'deck_boards', 'b', $qb->expr()->eq('s.board_id', 'b.id'))
                ->where($qb->expr()->eq('b.archived', $qb->createNamedParameter(0)))
                ->orderBy('s.board_id', 'ASC')
                ->addOrderBy('s.order', 'ASC')
                ->setMaxResults(1);
        }

        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        return ($row !== false && isset($row['id'])) ? (int)$row['id'] : null;
    }

    private function assignUserToCard(int $cardId, string $username): void {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->insert('deck_assigned_users')
                ->values([
                    'card_id' => $qb->createNamedParameter($cardId),
                    'participant' => $qb->createNamedParameter($username),
                ]);
            $qb->executeStatement();
        } catch (\Exception $e) {
            // User assignment failure is non-fatal for card creation
            $this->logger->debug("Could not assign user {$username} to card {$cardId}: " . $e->getMessage());
        }
    }
}
