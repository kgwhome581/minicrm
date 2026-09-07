<?php

declare(strict_types=1);

namespace OCA\MiniCRM\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

/**
 * @NoAdminRequired
 * @NoCSRFRequired
 */
#[NoCSRFRequired]
#[NoAdminRequired]
class PageController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Entry point for MiniCRM web UI inside Nextcloud.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function index(): TemplateResponse {
        Util::addScript('minicrm', 'minicrm-main');
        Util::addStyle('minicrm', 'minicrm-style');

        return new TemplateResponse('minicrm', 'main', [
            'appId' => 'minicrm',
        ]);
    }
}
