<?php

declare(strict_types=1);

return [
    'routes' => [
        // Web UI Route
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // REST API Endpoints for n8n & external integrations
        ['name' => 'api#ingestLead', 'url' => '/api/v1/leads/ingest', 'verb' => 'POST'],
        ['name' => 'api#findClient', 'url' => '/api/v1/clients/find', 'verb' => 'GET'],
        ['name' => 'api#findClient', 'url' => '/api/v1/clients/find', 'verb' => 'POST'],
        ['name' => 'api#createClient', 'url' => '/api/v1/clients', 'verb' => 'POST'],
        ['name' => 'api#updateClient', 'url' => '/api/v1/clients/{id}', 'verb' => 'POST'],
        ['name' => 'api#syncContact', 'url' => '/api/v1/clients/{id}/sync-contact', 'verb' => 'POST'],
        ['name' => 'api#getClient', 'url' => '/api/v1/clients/{id}', 'verb' => 'GET'],
        ['name' => 'api#listClients', 'url' => '/api/v1/clients', 'verb' => 'GET'],
        ['name' => 'api#createActivity', 'url' => '/api/v1/activities', 'verb' => 'POST'],
        ['name' => 'api#updateActivityStatus', 'url' => '/api/v1/activities/{id}/status', 'verb' => 'POST'],
        ['name' => 'api#logMessage', 'url' => '/api/v1/messages', 'verb' => 'POST'],
        ['name' => 'api#getClientTimeline', 'url' => '/api/v1/clients/{id}/timeline', 'verb' => 'GET'],
        ['name' => 'api#sendMessage', 'url' => '/api/v1/messages/send', 'verb' => 'POST'],
        ['name' => 'api#getConfig', 'url' => '/api/v1/config', 'verb' => 'GET'],
    ]
];
