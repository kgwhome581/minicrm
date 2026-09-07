<?php

declare(strict_types=1);

namespace OCA\MiniCRM\Service;

use OCP\Constants;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class FolderService {
    private const BASE_DIR = 'ViolaTax_Clients';

    public function __construct(
        private IRootFolder $rootFolder,
        private IUserManager $userManager,
        private IShareManager $shareManager,
        private IURLGenerator $urlGenerator,
        private IConfig $config,
        private PhoneNormalizer $phoneNormalizer,
        private LoggerInterface $logger
    ) {}

    /**
     * Get the designated storage owner in Nextcloud.
     */
    public function getStorageUser(?string $preferredUser = null): string {
        if (!empty($preferredUser) && $this->userManager->userExists($preferredUser)) {
            return $preferredUser;
        }

        $configuredUser = $this->config->getAppValue('minicrm', 'storage_user', '');
        if (!empty($configuredUser) && $this->userManager->userExists($configuredUser)) {
            return $configuredUser;
        }

        // Fallback: pick the first available admin or existing user
        $users = $this->userManager->search('', 1);
        if (!empty($users)) {
            return array_key_first($users);
        }

        return 'admin';
    }

    /**
     * Creates or gets client folder and activity subfolder, plus generates a File Drop upload link.
     *
     * @return array{folder_path: string, activity_folder_path: string, file_drop_url: string, share_token: string}
     */
    public function setupClientAndActivityFolder(
        string $clientName,
        string $clientUuid,
        string $activityUuid,
        ?string $responsibleUser = null,
        ?int $clientMiniCrmId = null,
        ?string $customActivityFolder = null
    ): array {
        $owner = $this->getStorageUser($responsibleUser);
        $userFolder = $this->rootFolder->getUserFolder($owner);

        $baseDir = $this->config->getAppValue('minicrm', 'base_folder', 'Users');

        $safeName = $this->phoneNormalizer->sanitizeName($clientName);
        if ($clientMiniCrmId !== null && $clientMiniCrmId > 0) {
            $clientFolderSegment = sprintf('%s - %d', $safeName, $clientMiniCrmId);
        } else {
            $clientFolderSegment = sprintf('%s_%s', $safeName, substr($clientUuid, 0, 8));
        }

        // 1. Base storage folder (e.g. Users)
        $baseFolder = $this->ensureSubFolder($userFolder, $baseDir);

        // 2. Client root folder (e.g. Ivan Petrov - 11)
        $clientFolder = $this->ensureSubFolder($baseFolder, $clientFolderSegment);

        // 3. Activity folder (e.g. 23_contact_2026-09-07_18-46-41 or activity UUID)
        $activitySubName = !empty($customActivityFolder) ? $this->phoneNormalizer->sanitizeName($customActivityFolder) : $activityUuid;
        $activityFolder = $this->ensureSubFolder($clientFolder, $activitySubName);

        // 4. Generate public File Drop share link for the activity folder
        $shareData = $this->createFileDropShare($activityFolder, $owner);

        $folderPath = '/' . $baseDir . '/' . $clientFolderSegment;
        $activityFolderPath = $folderPath . '/' . $activitySubName;

        return [
            'folder_path' => $folderPath,
            'activity_folder_path' => $activityFolderPath,
            'file_drop_url' => $shareData['url'],
            'share_token' => $shareData['token'],
        ];
    }

    /**
     * Create or retrieve a FileDrop (Upload Only) public share for an activity folder.
     *
     * @return array{url: string, token: string}
     */
    private function createFileDropShare(Folder $folder, string $owner): array {
        try {
            // Check existing shares first
            $existingShares = $this->shareManager->getSharesBy($owner, IShare::TYPE_LINK, $folder);
            foreach ($existingShares as $existing) {
                $token = $existing->getToken();
                return [
                    'url' => $this->buildShareUrl($token),
                    'token' => $token,
                ];
            }

            // Create new upload-only link share
            $share = $this->shareManager->newShare();
            $share->setNode($folder);
            $share->setShareType(IShare::TYPE_LINK);
            // Permissions: CREATE only = File drop / upload only (no read, no delete)
            $share->setPermissions(Constants::PERMISSION_CREATE);
            $share->setSharedBy($owner);

            $createdShare = $this->shareManager->createShare($share);
            $token = $createdShare->getToken();

            return [
                'url' => $this->buildShareUrl($token),
                'token' => $token,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to create FileDrop share: ' . $e->getMessage(), ['app' => 'minicrm']);
            return [
                'url' => '',
                'token' => '',
            ];
        }
    }

    private function buildShareUrl(string $token): string {
        try {
            return $this->urlGenerator->linkToRouteAbsolute('files_sharing.sharecontroller.showShare', ['token' => $token]);
        } catch (\Exception) {
            // Fallback standard Nextcloud public link
            return $this->urlGenerator->getBaseUrl() . '/s/' . $token;
        }
    }

    /**
     * Ensure a subfolder exists inside a parent Folder.
     */
    private function ensureSubFolder(Folder $parent, string $subFolderName): Folder {
        try {
            $node = $parent->get($subFolderName);
            if ($node instanceof Folder) {
                return $node;
            }
        } catch (NotFoundException) {
            // Does not exist yet, will create
        }

        try {
            return $parent->newFolder($subFolderName);
        } catch (NotPermittedException $e) {
            $this->logger->error("Not permitted to create folder {$subFolderName}: " . $e->getMessage(), ['app' => 'minicrm']);
            throw $e;
        }
    }
}
