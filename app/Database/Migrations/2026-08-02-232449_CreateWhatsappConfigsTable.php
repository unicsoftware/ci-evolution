<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWhatsappConfigsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'empresa_id' => [
                'type'       => 'INT',
                'null'       => false,
            ],
            'usuario_id' => [
                'type'       => 'INT',
                'null'       => false,
            ],
            'api_url' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => false,
            ],
            'api_key' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => false,
            ],
            'instance_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => false,
            ],
            'phone' => [
                'type'       => 'VARCHAR',
                'constraint' => '30',
                'null'       => true,
            ],
            'profile_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
            ],
            'profile_picture' => [
                'type'       => 'TEXT',
                'null'       => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'default'    => 'not_configured',
                'null'       => false,
            ],
            'connected' => [
                'type'       => 'BOOLEAN',
                'default'    => false,
                'null'       => false,
            ],
            'last_connection' => [
                'type'       => 'TIMESTAMP',
                'null'       => true,
            ],
            'created_at' => [
                'type'       => 'TIMESTAMP',
                'null'       => true,
            ],
            'updated_at' => [
                'type'       => 'TIMESTAMP',
                'null'       => true,
            ],
            'deleted_at' => [
                'type'       => 'TIMESTAMP',
                'null'       => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['empresa_id', 'usuario_id']);
        $this->forge->createTable('whatsapp_configs');
    }

    public function down()
    {
        $this->forge->dropTable('whatsapp_configs');
    }
}
