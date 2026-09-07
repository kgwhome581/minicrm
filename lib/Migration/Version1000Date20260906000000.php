<?php

declare(strict_types=1);

namespace OCA\MiniCRM\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20260906000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // 1. Clients Table
        if (!$schema->hasTable('minicrm_clients')) {
            $table = $schema->createTable('minicrm_clients');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 20,
            ]);
            $table->addColumn('uuid', Types::STRING, [
                'notnull' => true,
                'length' => 36,
            ]);
            $table->addColumn('full_name', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('phone', Types::STRING, [
                'notnull' => true,
                'length' => 50,
            ]);
            $table->addColumn('phone_raw', Types::STRING, [
                'notnull' => false,
                'length' => 50,
            ]);
            $table->addColumn('email', Types::STRING, [
                'notnull' => false,
                'length' => 255,
            ]);
            $table->addColumn('folder_path', Types::STRING, [
                'notnull' => false,
                'length' => 512,
            ]);
            $table->addColumn('notes', Types::TEXT, [
                'notnull' => false,
            ]);
            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => false,
            ]);
            $table->addColumn('updated_at', Types::DATETIME, [
                'notnull' => false,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['uuid'], 'minicrm_client_uuid_idx');
            $table->addIndex(['phone'], 'minicrm_client_phone_idx');
            $table->addIndex(['email'], 'minicrm_client_email_idx');
        }

        // 2. Activities (Meetings / Deals) Table
        if (!$schema->hasTable('minicrm_activities')) {
            $table = $schema->createTable('minicrm_activities');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 20,
            ]);
            $table->addColumn('activity_uuid', Types::STRING, [
                'notnull' => true,
                'length' => 36,
            ]);
            $table->addColumn('client_id', Types::BIGINT, [
                'notnull' => true,
                'length' => 20,
            ]);
            $table->addColumn('source', Types::STRING, [
                'notnull' => false,
                'length' => 64,
                'default' => 'easypoint',
            ]);
            $table->addColumn('status', Types::STRING, [
                'notnull' => true,
                'length' => 64,
                'default' => 'scheduled',
            ]);
            $table->addColumn('responsible_user', Types::STRING, [
                'notnull' => false,
                'length' => 64,
            ]);
            $table->addColumn('deck_task_id', Types::BIGINT, [
                'notnull' => false,
                'length' => 20,
            ]);
            $table->addColumn('calendar_event_id', Types::STRING, [
                'notnull' => false,
                'length' => 255,
            ]);
            $table->addColumn('meeting_time', Types::DATETIME, [
                'notnull' => false,
            ]);
            $table->addColumn('file_drop_url', Types::STRING, [
                'notnull' => false,
                'length' => 512,
            ]);
            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => false,
            ]);
            $table->addColumn('updated_at', Types::DATETIME, [
                'notnull' => false,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['activity_uuid'], 'minicrm_act_uuid_idx');
            $table->addIndex(['client_id'], 'minicrm_act_client_idx');
            $table->addIndex(['status'], 'minicrm_act_status_idx');
        }

        // 3. Client Identities (Cross-channel resolution: Telegram chat_id, WhatsApp phone, etc.)
        if (!$schema->hasTable('minicrm_client_identities')) {
            $table = $schema->createTable('minicrm_client_identities');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 20,
            ]);
            $table->addColumn('client_id', Types::BIGINT, [
                'notnull' => true,
                'length' => 20,
            ]);
            $table->addColumn('channel', Types::STRING, [
                'notnull' => true,
                'length' => 32, // telegram, whatsapp, facebook, email
            ]);
            $table->addColumn('external_id', Types::STRING, [
                'notnull' => true,
                'length' => 255, // chat_id, phone, psid
            ]);
            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => false,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['client_id'], 'minicrm_ident_client_idx');
            $table->addUniqueIndex(['channel', 'external_id'], 'minicrm_ident_chan_ext_idx');
        }

        // 4. Multichannel Messages Table
        if (!$schema->hasTable('minicrm_messages')) {
            $table = $schema->createTable('minicrm_messages');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 20,
            ]);
            $table->addColumn('client_id', Types::BIGINT, [
                'notnull' => true,
                'length' => 20,
            ]);
            $table->addColumn('activity_id', Types::BIGINT, [
                'notnull' => false,
                'length' => 20,
            ]);
            $table->addColumn('channel', Types::STRING, [
                'notnull' => true,
                'length' => 32, // email, telegram, whatsapp, facebook
                'default' => 'email',
            ]);
            $table->addColumn('direction', Types::STRING, [
                'notnull' => true,
                'length' => 16, // inbound, outbound
                'default' => 'inbound',
            ]);
            $table->addColumn('sender_recipient', Types::STRING, [
                'notnull' => false,
                'length' => 255,
            ]);
            $table->addColumn('subject', Types::STRING, [
                'notnull' => false,
                'length' => 255,
            ]);
            $table->addColumn('content', Types::TEXT, [
                'notnull' => false,
            ]);
            $table->addColumn('attachments', Types::TEXT, [
                'notnull' => false, // JSON encoded array of file paths / names
            ]);
            $table->addColumn('external_message_id', Types::STRING, [
                'notnull' => false,
                'length' => 255,
            ]);
            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => false,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['client_id'], 'minicrm_msg_client_idx');
            $table->addIndex(['activity_id'], 'minicrm_msg_act_idx');
            $table->addIndex(['external_message_id'], 'minicrm_msg_ext_id_idx');
        }

        return $schema;
    }
}
