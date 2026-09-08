<?php

declare(strict_types=1);

namespace OCA\MiniCRM\Controller;

use DateTime;
use OCA\MiniCRM\Db\Activity;
use OCA\MiniCRM\Db\ActivityMapper;
use OCA\MiniCRM\Db\Client;
use OCA\MiniCRM\Db\ClientMapper;
use OCA\MiniCRM\Db\Identity;
use OCA\MiniCRM\Db\IdentityMapper;
use OCA\MiniCRM\Db\Message;
use OCA\MiniCRM\Db\MessageMapper;
use OCA\MiniCRM\Service\ContactBridgeService;
use OCA\MiniCRM\Service\FolderService;
use OCA\MiniCRM\Service\IngestionService;
use OCA\MiniCRM\Service\PhoneNormalizer;
use OCP\AppFramework\ApiController as BaseApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @NoAdminRequired
 * @NoCSRFRequired
 * @PublicPage
 */
#[NoCSRFRequired]
#[NoAdminRequired]
#[PublicPage]
class ApiController extends BaseApiController {
    public function __construct(
        string $appName,
        IRequest $request,
        private IngestionService $ingestionService,
        private ClientMapper $clientMapper,
        private ActivityMapper $activityMapper,
        private IdentityMapper $identityMapper,
        private MessageMapper $messageMapper,
        private PhoneNormalizer $phoneNormalizer,
        private FolderService $folderService,
        private IConfig $config,
        private IUserSession $userSession,
        private LoggerInterface $logger,
        private ?ContactBridgeService $contactBridge = null
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Validate authentication via Bearer token or active Nextcloud session.
     */
    private function isAuthorized(): bool {
        // 1. Check if user is logged into Nextcloud web UI
        if ($this->userSession->isLoggedIn()) {
            return true;
        }

        // 2. Check Bearer token from n8n or external integration
        $expectedToken = $this->config->getAppValue('minicrm', 'api_token', '');
        if (!empty($expectedToken)) {
            // Check Authorization header
            $authHeader = (string)$this->request->getHeader('Authorization');
            if (empty($authHeader)) {
                $authHeader = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
            }

            if (!empty($authHeader) && str_starts_with($authHeader, 'Bearer ')) {
                $token = trim(substr($authHeader, 7));
                if (hash_equals($expectedToken, $token)) {
                    return true;
                }
            }

            // Check X-API-TOKEN or X-Auth-Token headers
            $xApiToken = (string)($this->request->getHeader('X-API-TOKEN') ?? $this->request->getHeader('X-Auth-Token') ?? '');
            if (!empty($xApiToken) && hash_equals($expectedToken, $xApiToken)) {
                return true;
            }

            // Also check query param ?token=... or ?api_token=... for easy webhook testing
            $tokenParam = (string)($this->request->getParam('token') ?? $this->request->getParam('api_token') ?? '');
            if (!empty($tokenParam) && hash_equals($expectedToken, $tokenParam)) {
                return true;
            }

            return false;
        }

        return true;
    }

    /**
     * POST /api/v1/leads/ingest
     * Atomic endpoint for n8n when Easypoint webhook triggers.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    #[NoCSRFRequired]
    #[NoAdminRequired]
    #[PublicPage]
    public function ingestLead(): DataResponse {
        if (!$this->isAuthorized()) {
            return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
        }

        $params = $this->request->getParams();
        if (empty($params) || (!isset($params['customer_first_name']) && !isset($params['client_name']) && !isset($params['customer_phone']) && !isset($params['phone']))) {
            $rawBody = file_get_contents('php://input');
            if (!empty($rawBody)) {
                $decoded = json_decode($rawBody, true);
                if (is_array($decoded)) {
                    $params = array_merge($params, $decoded);
                }
            }
        }
        try {
            $result = $this->ingestionService->ingestLead($params);
            return new DataResponse($result, Http::STATUS_CREATED);
        } catch (\Throwable $e) {
            return new DataResponse([
                'error' => $e->getMessage(),
                'status' => 'error',
            ], Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * GET / POST /api/v1/clients/find
     * Search client by phone, email, or external customer_id.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    #[NoCSRFRequired]
    #[NoAdminRequired]
    #[PublicPage]
    public function findClient(): DataResponse {
        if (!$this->isAuthorized()) {
            return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
        }

        $phone = $this->request->getParam('phone') ?? $this->request->getParam('customer_phone');
        $email = $this->request->getParam('email') ?? $this->request->getParam('customer_email');
        $channel = $this->request->getParam('channel', 'easyappointments');
        $externalId = $this->request->getParam('external_id') ?? $this->request->getParam('customer_id');

        $client = null;
        $matchedBy = null;

        // 1. If externalId provided (e.g. easyappointments customer_id 11 or telegram chat_id)
        if (!empty($externalId)) {
            $identity = $this->identityMapper->findByChannelAndExternalId((string)$channel, (string)$externalId);
            if ($identity !== null) {
                try {
                    $client = $this->clientMapper->find($identity->getClientId());
                    $matchedBy = 'identity';
                } catch (\Exception) {}
            }
        }

        // 2. Lookup by phone or email
        if ($client === null && (!empty($phone) || !empty($email))) {
            $normalizedPhone = $this->phoneNormalizer->normalize($phone ? (string)$phone : null);
            $cleanEmail = !empty($email) ? strtolower(trim((string)$email)) : null;
            $client = $this->clientMapper->findByPhoneOrEmail($normalizedPhone, $cleanEmail);
            if ($client !== null) {
                $matchedBy = 'contact';
            }
        }

        if ($client !== null) {
            $clientData = $client->jsonSerialize();
            $clientData['customer_mini_crm_id'] = $client->getId();

            $contactCard = $this->contactBridge
                ? $this->contactBridge->getContactInfo(
                    $client->getUuid(),
                    $client->getEmail(),
                    $client->getPhone(),
                    $client->getFullName(),
                    $client->getNotes()
                )
                : null;

            return new DataResponse([
                'found' => true,
                'customer_mini_crm_id' => $client->getId(),
                'client' => $clientData,
                'customer_file_path' => $client->getFolderPath(),
                'contact_card' => $contactCard,
                'matched_by' => $matchedBy,
            ], Http::STATUS_OK);
        }

        return new DataResponse([
            'found' => false,
            'customer_mini_crm_id' => null,
            'client' => null,
            'customer_file_path' => null,
            'contact_card' => null,
        ], Http::STATUS_NOT_FOUND);
    }

    /**
     * POST /api/v1/clients
     * Create client record.
     */
    #[NoCSRFRequired]
    #[NoAdminRequired]
    #[PublicPage]
    public function createClient(): DataResponse {
        if (!$this->isAuthorized()) {
            return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
        }

        $fullName = trim((string)$this->request->getParam('full_name', ''));
        if (empty($fullName)) {
            return new DataResponse(['error' => 'full_name is required'], Http::STATUS_BAD_REQUEST);
        }

        $phoneRaw = $this->request->getParam('phone');
        $phoneNormalized = $this->phoneNormalizer->normalize($phoneRaw);
        $email = $this->request->getParam('email');

        $client = new Client();
        $client->setUuid($this->generateUuid());
        $client->setFullName($fullName);
        $client->setPhone($phoneNormalized);
        $client->setPhoneRaw($phoneRaw);
        $client->setEmail(!empty($email) ? strtolower(trim((string)$email)) : null);
        $client->setNotes($this->request->getParam('notes'));
        $client->setCreatedAt(new DateTime('now'));
        $client->setUpdatedAt(new DateTime('now'));

        $savedClient = $this->clientMapper->insert($client);
        return new DataResponse($savedClient->jsonSerialize(), Http::STATUS_CREATED);
    }

    /**
     * POST /api/v1/clients/{id}
     * Update client information.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    #[NoCSRFRequired]
    #[NoAdminRequired]
    #[PublicPage]
    public function updateClient(int $id): DataResponse {
        if (!$this->isAuthorized()) {
            return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $client = $this->clientMapper->find($id);

            $fullName = $this->request->getParam('full_name');
            if (!empty($fullName)) {
                $client->setFullName(trim((string)$fullName));
            }

            $phone = $this->request->getParam('phone');
            if (!empty($phone)) {
                $client->setPhone($this->phoneNormalizer->normalize($phone));
                $client->setPhoneRaw((string)$phone);
            }

            $email = $this->request->getParam('email');
            if ($email !== null) {
                $client->setEmail(!empty($email) ? strtolower(trim((string)$email)) : null);
            }

            $notes = $this->request->getParam('notes');
            if ($notes !== null) {
                $client->setNotes((string)$notes);
            }

            $client->setUpdatedAt(new DateTime('now'));
            $saved = $this->clientMapper->update($client);

            // Sync updated contact into Nextcloud Contacts
            if ($this->contactBridge !== null) {
                try {
                    $activities = $this->activityMapper->findByClientId($id);
                    $responsibleUser = (!empty($activities) && !empty($activities[0]->getResponsibleUser()))
                        ? $activities[0]->getResponsibleUser()
                        : 'admin';
                    $this->contactBridge->syncContact(
                        $responsibleUser,
                        $saved,
                        $saved->getFolderPath() ? '/apps/files/?dir=' . urlencode($saved->getFolderPath()) : null
                    );
                } catch (\Exception $e) {
                    $this->logger->debug('Contact sync during client update: ' . $e->getMessage(), ['app' => 'minicrm']);
                }
            }

            return new DataResponse($saved->jsonSerialize(), Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Client not found or update failed: ' . $e->getMessage()], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/clients/{id}/sync-contact
     * Manually or dynamically sync client to Nextcloud Contacts module.
     */
    #[NoCSRFRequired]
    #[NoAdminRequired]
    #[PublicPage]
    public function syncContact(int $id): DataResponse {
        if (!$this->isAuthorized()) {
            return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
        }

        if ($this->contactBridge === null) {
            return new DataResponse(['error' => 'Contact service is not available'], Http::STATUS_SERVICE_UNAVAILABLE);
        }

        try {
            $client = $this->clientMapper->find($id);
            $activities = $this->activityMapper->findByClientId($id);
            $responsibleUser = (!empty($activities) && !empty($activities[0]->getResponsibleUser()))
                ? $activities[0]->getResponsibleUser()
                : 'admin';

            $folderUrl = $client->getFolderPath() ? '/apps/files/?dir=' . urlencode($client->getFolderPath()) : null;
            $contactInfo = $this->contactBridge->syncContact($responsibleUser, $client, $folderUrl);

            return new DataResponse([
                'status' => 'success',
                'contact' => $contactInfo,
            ], Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Sync failed: ' . $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/v1/clients/{id}
     */
    #[NoCSRFRequired]
    #[NoAdminRequired]
    #[PublicPage]
    public function getClient(int $id): DataResponse {
        if (!$this->isAuthorized()) {
            return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $client = $this->clientMapper->find($id);
            $activities = $this->activityMapper->findByClientId($id);
            $identities = $this->identityMapper->findByClientId($id);
            $contactCard = $this->contactBridge
                ? $this->contactBridge->getContactInfo(
                    $client->getUuid(),
                    $client->getEmail(),
                    $client->getPhone(),
                    $client->getFullName(),
                    $client->getNotes()
                )
                : ['exists' => false, 'app_url' => null, 'address' => null];

            return new DataResponse([
                'client' => $client->jsonSerialize(),
                'activities' => array_map(fn($a) => $a->jsonSerialize(), $activities),
                'identities' => array_map(fn($i) => $i->jsonSerialize(), $identities),
                'contact_card' => $contactCard,
            ]);
        } catch (\Exception) {
            return new DataResponse(['error' => 'Client not found'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * GET /api/v1/clients
     */
    #[NoCSRFRequired]
    #[NoAdminRequired]
    #[PublicPage]
    public function listClients(): DataResponse {
        if (!$this->isAuthorized()) {
            return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
        }

        $query = (string)$this->request->getParam('search', '');
        $limit = min(100, max(1, (int)$this->request->getParam('limit', 50)));
        $offset = max(0, (int)$this->request->getParam('offset', 0));

        $clients = empty($query)
            ? $this->clientMapper->search('', $limit, $offset)
            : $this->clientMapper->search($query, $limit, $offset);

        return new DataResponse([
            'clients' => array_map(fn($c) => $c->jsonSerialize(), $clients),
            'count' => count($clients),
        ]);
    }

    /**
     * POST /api/v1/activities
     */
    #[NoCSRFRequired]
    #[NoAdminRequired]
    #[PublicPage]
    public function createActivity(): DataResponse {
        if (!$this->isAuthorized()) {
            return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
        }

        $clientId = (int)$this->request->getParam('client_id');
        if ($clientId <= 0) {
            return new DataResponse(['error' => 'client_id is required'], Http::STATUS_BAD_REQUEST);
        }

        $activity = new Activity();
        $activity->setActivityUuid($this->generateUuid());
        $activity->setClientId($clientId);
        $activity->setSource((string)$this->request->getParam('source', 'manual'));
        $activity->setStatus((string)$this->request->getParam('status', 'scheduled'));
        $activity->setResponsibleUser($this->request->getParam('responsible_user'));
        $activity->setCreatedAt(new DateTime('now'));
        $activity->setUpdatedAt(new DateTime('now'));

        $saved = $this->activityMapper->insert($activity);
        return new DataResponse($saved->jsonSerialize(), Http::STATUS_CREATED);
    }

    /**
     * POST /api/v1/activities/{id}/status
     */
    #[NoCSRFRequired]
    #[NoAdminRequired]
    #[PublicPage]
    public function updateActivityStatus(int $id): DataResponse {
        if (!$this->isAuthorized()) {
            return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
        }

        $status = (string)$this->request->getParam('status');
        if (empty($status)) {
            return new DataResponse(['error' => 'status is required'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $activity = $this->activityMapper->find($id);
            $activity->setStatus($status);
            $activity->setUpdatedAt(new DateTime('now'));
            $saved = $this->activityMapper->update($activity);

            return new DataResponse($saved->jsonSerialize());
        } catch (\Exception) {
            return new DataResponse(['error' => 'Activity not found'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/messages
     * Logs inbound/outbound message (Email, Telegram, WhatsApp, Facebook).
     * Includes deduplication check.
     */
    #[NoCSRFRequired]
    #[NoAdminRequired]
    #[PublicPage]
    public function logMessage(): DataResponse {
        if (!$this->isAuthorized()) {
            return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
        }

        $clientId = (int)$this->request->getParam('client_id');
        $externalMessageId = (string)$this->request->getParam('external_message_id', '');

        // Idempotency check
        if (!empty($externalMessageId) && $this->messageMapper->existsByExternalId($externalMessageId)) {
            return new DataResponse(['status' => 'duplicate_skipped'], Http::STATUS_OK);
        }

        // If client_id not directly known, attempt lookup by channel identity
        if ($clientId <= 0) {
            $channel = (string)$this->request->getParam('channel');
            $sender = (string)$this->request->getParam('sender_recipient');
            if (!empty($channel) && !empty($sender)) {
                $ident = $this->identityMapper->findByChannelAndExternalId($channel, $sender);
                if ($ident !== null) {
                    $clientId = $ident->getClientId();
                } else {
                    // Try phone lookup
                    $normalized = $this->phoneNormalizer->normalize($sender);
                    $foundClient = $this->clientMapper->findByPhoneOrEmail($normalized, null);
                    if ($foundClient !== null) {
                        $clientId = (int)$foundClient->getId();
                    }
                }
            }
        }

        if ($clientId <= 0) {
            return new DataResponse(['error' => 'Unable to associate message with a client'], Http::STATUS_UNPROCESSABLE_ENTITY);
        }

        $activityId = $this->request->getParam('activity_id');
        if (empty($activityId)) {
            $latestAct = $this->activityMapper->findLatestForClient($clientId);
            $activityId = $latestAct?->getId();
        }

        $attachments = $this->request->getParam('attachments');
        $attachmentsArray = is_array($attachments) ? $attachments : [];

        $msg = new Message();
        $msg->setClientId($clientId);
        $msg->setActivityId($activityId ? (int)$activityId : null);
        $msg->setChannel((string)$this->request->getParam('channel', 'email'));
        $msg->setDirection((string)$this->request->getParam('direction', 'inbound'));
        $msg->setSenderRecipient($this->request->getParam('sender_recipient'));
        $msg->setSubject($this->request->getParam('subject'));
        $msg->setContent($this->request->getParam('content'));
        $msg->setAttachmentsList($attachmentsArray);
        $msg->setExternalMessageId($externalMessageId ?: null);
        $msg->setCreatedAt(new DateTime('now'));

        $saved = $this->messageMapper->insert($msg);
        return new DataResponse($saved->jsonSerialize(), Http::STATUS_CREATED);
    }

    /**
     * GET /api/v1/clients/{id}/timeline
     */
    #[NoCSRFRequired]
    #[NoAdminRequired]
    #[PublicPage]
    public function getClientTimeline(int $id): DataResponse {
        if (!$this->isAuthorized()) {
            return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
        }

        $limit = min(200, max(1, (int)$this->request->getParam('limit', 100)));
        $offset = max(0, (int)$this->request->getParam('offset', 0));

        $messages = $this->messageMapper->getTimelineForClient($id, $limit, $offset);

        return new DataResponse([
            'client_id' => $id,
            'messages' => array_map(fn($m) => $m->jsonSerialize(), $messages),
            'count' => count($messages),
        ]);
    }

    /**
     * POST /api/v1/messages/send
     * Dispatch an outbound message from MiniCRM UI to n8n webhook for delivery.
     */
    #[NoCSRFRequired]
    #[NoAdminRequired]
    #[PublicPage]
    public function sendMessage(): DataResponse {
        if (!$this->isAuthorized()) {
            return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
        }

        $clientId = (int)$this->request->getParam('client_id');
        $channel = (string)$this->request->getParam('channel', 'email');
        $recipient = (string)$this->request->getParam('recipient', '');
        $content = (string)$this->request->getParam('content', '');
        $subject = $this->request->getParam('subject');

        if ($clientId <= 0 || empty($content)) {
            return new DataResponse(['error' => 'client_id and content are required'], Http::STATUS_BAD_REQUEST);
        }

        // Record message as outbound in MiniCRM
        $msg = new Message();
        $msg->setClientId($clientId);
        $msg->setChannel($channel);
        $msg->setDirection('outbound');
        $msg->setSenderRecipient($recipient);
        $msg->setSubject($subject);
        $msg->setContent($content);
        $msg->setCreatedAt(new DateTime('now'));
        $saved = $this->messageMapper->insert($msg);

        // Forward to n8n outbound webhook if configured
        $n8nWebhookUrl = $this->config->getAppValue('minicrm', 'n8n_outbound_webhook_url', 'https://n8n.violatax.ca/webhook/outbound-message');
        if (!empty($n8nWebhookUrl)) {
            $this->dispatchToN8n($n8nWebhookUrl, [
                'message_id' => $saved->getId(),
                'client_id' => $clientId,
                'channel' => $channel,
                'recipient' => $recipient,
                'subject' => $subject,
                'content' => $content,
            ]);
        }

        return new DataResponse($saved->jsonSerialize(), Http::STATUS_CREATED);
    }

    /**
     * GET /api/v1/config
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    #[NoCSRFRequired]
    #[NoAdminRequired]
    #[PublicPage]
    public function getConfig(): DataResponse {
        return new DataResponse([
            'app' => 'minicrm',
            'version' => '1.0.0',
            'storage_user' => $this->folderService->getStorageUser(),
            'default_timezone' => 'America/Edmonton',
        ]);
    }

    private function dispatchToN8n(string $url, array $payload): void {
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to dispatch to n8n webhook: ' . $e->getMessage());
        }
    }

    private function generateUuid(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
