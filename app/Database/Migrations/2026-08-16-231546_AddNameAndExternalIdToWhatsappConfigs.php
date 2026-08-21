<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNameAndExternalIdToWhatsappConfigs extends Migration
{
    public function up()
    {
        $fields = [
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
                'after'      => 'usuario_id'
            ],
            'external_id' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
                'after'      => 'status'
            ],
        ];
        $this->forge->addColumn('whatsapp_configs', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('whatsapp_configs', 'name');
        $this->forge->dropColumn('whatsapp_configs', 'external_id');
    }
}
