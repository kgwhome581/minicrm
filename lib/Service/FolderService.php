<?php

declare(strict_types=1);

namespace OCA\MiniCRM\Service;

use OCA\MiniCRM\Db\Client;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
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

    /**
     * List all files and folders in a client's folder, including subfolders.
     *
     * @return array{folder_path: string, current_subpath: string, all_subfolders: string[], files: array, folders: array}
     */
    public function listClientFiles(Client $client, ?string $subPath = null): array {
        $owner = $this->getStorageUser();
        try {
            $userFolder = $this->rootFolder->getUserFolder($owner);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get user folder: ' . $e->getMessage(), ['app' => 'minicrm']);
            return ['folder_path' => '', 'current_subpath' => '', 'all_subfolders' => [], 'files' => [], 'folders' => []];
        }

        $folderPath = $client->getFolderPath();
        if (empty($folderPath)) {
            return ['folder_path' => '', 'current_subpath' => '', 'all_subfolders' => [], 'files' => [], 'folders' => []];
        }

        $baseRelative = ltrim($folderPath, '/');
        $currentRelative = $baseRelative;
        $cleanSub = '';
        if (!empty($subPath)) {
            $cleanSub = trim(str_replace(['..', '\\'], ['', '/'], $subPath), '/');
            if (!empty($cleanSub)) {
                $currentRelative .= '/' . $cleanSub;
            }
        }

        try {
            if (!$userFolder->nodeExists($currentRelative)) {
                return ['folder_path' => $folderPath, 'current_subpath' => $cleanSub, 'all_subfolders' => [], 'files' => [], 'folders' => []];
            }

            $folderNode = $userFolder->get($currentRelative);
            if (!$folderNode instanceof Folder) {
                return ['folder_path' => $folderPath, 'current_subpath' => $cleanSub, 'all_subfolders' => [], 'files' => [], 'folders' => []];
            }

            $items = $folderNode->getDirectoryListing();
            $files = [];
            $folders = [];
            $allSubfolders = [];

            // If inside a subfolder, inspect root folder for all subfolder options
            if ($currentRelative !== $baseRelative) {
                try {
                    $rootNode = $userFolder->get($baseRelative);
                    if ($rootNode instanceof Folder) {
                        foreach ($rootNode->getDirectoryListing() as $rItem) {
                            if ($rItem instanceof Folder) {
                                $allSubfolders[] = $rItem->getName();
                            }
                        }
                    }
                } catch (\Throwable) {}
            }

            foreach ($items as $item) {
                $name = $item->getName();
                $mtime = $item->getMTime();
                $mtimeIso = date('Y-m-d H:i:s', $mtime);

                if ($item instanceof Folder) {
                    $subFiles = [];
                    try {
                        foreach ($item->getDirectoryListing() as $child) {
                            if ($child instanceof File) {
                                $subFiles[] = [
                                    'name' => $child->getName(),
                                    'path' => $child->getPath(),
                                    'size' => $child->getSize(),
                                    'size_formatted' => $this->formatFileSize($child->getSize()),
                                    'mtime' => $child->getMTime(),
                                    'mtime_formatted' => date('Y-m-d H:i:s', $child->getMTime()),
                                    'mimetype' => $child->getMimetype(),
                                    'extension' => strtolower(pathinfo($child->getName(), PATHINFO_EXTENSION)),
                                    'subfolder' => $name,
                                ];
                            }
                        }
                    } catch (\Throwable) {}

                    $allSubfolders[] = $name;
                    $folders[] = [
                        'name' => $name,
                        'path' => $item->getPath(),
                        'mtime' => $mtime,
                        'mtime_formatted' => $mtimeIso,
                        'count' => count($subFiles),
                        'files' => $subFiles,
                    ];
                } else if ($item instanceof File) {
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $files[] = [
                        'name' => $name,
                        'path' => $item->getPath(),
                        'size' => $item->getSize(),
                        'size_formatted' => $this->formatFileSize($item->getSize()),
                        'mtime' => $mtime,
                        'mtime_formatted' => $mtimeIso,
                        'mimetype' => $item->getMimetype(),
                        'extension' => $ext,
                        'subfolder' => $cleanSub,
                    ];
                }
            }

            return [
                'folder_path' => $folderPath,
                'current_subpath' => $cleanSub,
                'all_subfolders' => array_values(array_unique($allSubfolders)),
                'files' => $files,
                'folders' => $folders,
            ];
        } catch (NotFoundException) {
            return ['folder_path' => $folderPath, 'current_subpath' => $cleanSub, 'all_subfolders' => [], 'files' => [], 'folders' => []];
        } catch (\Throwable $e) {
            $this->logger->error('Error listing client files: ' . $e->getMessage(), ['app' => 'minicrm']);
            return ['folder_path' => $folderPath, 'current_subpath' => $cleanSub, 'all_subfolders' => [], 'error' => $e->getMessage(), 'files' => [], 'folders' => []];
        }
    }

    /**
     * Upload a file into the client's root or activity subfolder.
     */
    public function uploadClientFile(Client $client, string $fileName, string $tmpFilePath, ?string $subFolder = null): array {
        $owner = $this->getStorageUser();
        $userFolder = $this->rootFolder->getUserFolder($owner);
        $folderPath = $client->getFolderPath();

        if (empty($folderPath)) {
            $baseDir = $this->config->getAppValue('minicrm', 'base_folder', 'Users');
            $baseFolder = $this->ensureSubFolder($userFolder, $baseDir);
            $safeName = $this->phoneNormalizer->sanitizeName($client->getFullName());
            $clientFolderSegment = sprintf('%s - %d', $safeName, $client->getId());
            $clientFolder = $this->ensureSubFolder($baseFolder, $clientFolderSegment);
            $folderPath = '/' . $baseDir . '/' . $clientFolderSegment;
            $client->setFolderPath($folderPath);
        } else {
            $relative = ltrim($folderPath, '/');
            if (!$userFolder->nodeExists($relative)) {
                $baseDir = $this->config->getAppValue('minicrm', 'base_folder', 'Users');
                $baseFolder = $this->ensureSubFolder($userFolder, $baseDir);
                $safeName = $this->phoneNormalizer->sanitizeName($client->getFullName());
                $clientFolderSegment = sprintf('%s - %d', $safeName, $client->getId());
                $clientFolder = $this->ensureSubFolder($baseFolder, $clientFolderSegment);
            } else {
                $clientFolder = $userFolder->get($relative);
            }
        }

        if (!$clientFolder instanceof Folder) {
            throw new \Exception('Client root node is not a folder');
        }

        $targetFolder = $clientFolder;
        if (!empty($subFolder)) {
            $cleanSub = trim(str_replace(['..', '\\'], ['', '/'], $subFolder), '/');
            if (!empty($cleanSub)) {
                $targetFolder = $this->ensureSubFolder($clientFolder, $cleanSub);
            }
        }

        $cleanFileName = basename($fileName);
        $cleanFileName = preg_replace('/[^\w\s\.-]/u', '_', $cleanFileName);

        $content = file_get_contents($tmpFilePath);
        if ($content === false) {
            throw new \Exception('Failed to read uploaded temporary file');
        }

        if ($targetFolder->nodeExists($cleanFileName)) {
            $existing = $targetFolder->get($cleanFileName);
            if ($existing instanceof File) {
                $existing->putContent($content);
                $node = $existing;
            } else {
                throw new \Exception('A folder with this name already exists');
            }
        } else {
            $node = $targetFolder->newFile($cleanFileName, $content);
        }

        return [
            'name' => $cleanFileName,
            'size' => $node->getSize(),
            'size_formatted' => $this->formatFileSize($node->getSize()),
            'mtime' => $node->getMTime(),
            'mtime_formatted' => date('Y-m-d H:i:s', $node->getMTime()),
            'mimetype' => $node->getMimetype(),
            'extension' => strtolower(pathinfo($cleanFileName, PATHINFO_EXTENSION)),
            'subfolder' => !empty($subFolder) ? trim($subFolder, '/') : '',
        ];
    }

    /**
     * Get a specific file node for downloading.
     */
    public function getClientFileNode(Client $client, string $fileName, ?string $subFolder = null): ?File {
        $owner = $this->getStorageUser();
        try {
            $userFolder = $this->rootFolder->getUserFolder($owner);
            $folderPath = $client->getFolderPath();
            if (empty($folderPath)) {
                return null;
            }

            $relative = ltrim($folderPath, '/');
            if (!empty($subFolder)) {
                $cleanSub = trim(str_replace(['..', '\\'], ['', '/'], $subFolder), '/');
                if (!empty($cleanSub)) {
                    $relative .= '/' . $cleanSub;
                }
            }
            $cleanName = basename($fileName);
            $relative .= '/' . $cleanName;

            if ($userFolder->nodeExists($relative)) {
                $node = $userFolder->get($relative);
                if ($node instanceof File) {
                    return $node;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error getting file node: ' . $e->getMessage(), ['app' => 'minicrm']);
        }

        return null;
    }

    /**
     * Delete a file from client directory.
     */
    public function deleteClientFile(Client $client, string $fileName, ?string $subFolder = null): bool {
        $fileNode = $this->getClientFileNode($client, $fileName, $subFolder);
        if ($fileNode !== null) {
            $fileNode->delete();
            return true;
        }
        return false;
    }

    /**
     * Human readable file size formatter.
     */
    public function formatFileSize(int $bytes): string {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
