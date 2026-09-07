<?php

declare(strict_types=1);

namespace OCA\MiniCRM\AppInfo;

use OCA\MiniCRM\Db\ActivityMapper;
use OCA\MiniCRM\Db\ClientMapper;
use OCA\MiniCRM\Db\IdentityMapper;
use OCA\MiniCRM\Db\MessageMapper;
use OCA\MiniCRM\Service\CalendarBridgeService;
use OCA\MiniCRM\Service\DeckBridgeService;
use OCA\MiniCRM\Service\FolderService;
use OCA\MiniCRM\Service\IngestionService;
use OCA\MiniCRM\Service\PhoneNormalizer;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
    public const APP_ID = 'minicrm';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        // Register Service Singletons in DI Container
        $context->registerService(PhoneNormalizer::class, function () {
            return new PhoneNormalizer();
        });

        $context->registerService(ClientMapper::class, function ($c) {
            return new ClientMapper($c->get('OCP\IDBConnection'));
        });

        $context->registerService(ActivityMapper::class, function ($c) {
            return new ActivityMapper($c->get('OCP\IDBConnection'));
        });

        $context->registerService(IdentityMapper::class, function ($c) {
            return new IdentityMapper($c->get('OCP\IDBConnection'));
        });

        $context->registerService(MessageMapper::class, function ($c) {
            return new MessageMapper($c->get('OCP\IDBConnection'));
        });

        $context->registerService(FolderService::class, function ($c) {
            return new FolderService(
                $c->get('OCP\Files\IRootFolder'),
                $c->get('OCP\IUserManager'),
                $c->get('OCP\Share\IManager'),
                $c->get('OCP\IURLGenerator'),
                $c->get('OCP\IConfig'),
                $c->get(PhoneNormalizer::class),
                $c->get('Psr\Log\LoggerInterface')
            );
        });

        $context->registerService(CalendarBridgeService::class, function ($c) {
            return new CalendarBridgeService(
                $c->get('OCP\IDBConnection'),
                $c->get('Psr\Log\LoggerInterface')
            );
        });

        $context->registerService(DeckBridgeService::class, function ($c) {
            return new DeckBridgeService(
                $c->get('OCP\IDBConnection'),
                $c->get('OCP\IConfig'),
                $c->get('Psr\Log\LoggerInterface')
            );
        });

        $context->registerService(IngestionService::class, function ($c) {
            return new IngestionService(
                $c->get(ClientMapper::class),
                $c->get(ActivityMapper::class),
                $c->get(IdentityMapper::class),
                $c->get(MessageMapper::class),
                $c->get(PhoneNormalizer::class),
                $c->get(FolderService::class),
                $c->get(CalendarBridgeService::class),
                $c->get(DeckBridgeService::class),
                $c->get('OCP\IDBConnection'),
                $c->get('Psr\Log\LoggerInterface')
            );
        });
    }

    public function boot(IBootContext $context): void {
        // App lifecycle boot hooks if needed
    }
}
