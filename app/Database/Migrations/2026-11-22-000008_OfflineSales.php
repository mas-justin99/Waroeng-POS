<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Transaksi offline: id unik dari perangkat (anti dobel saat sinkron) + nomor struk sementara. */
class OfflineSales extends Migration
{
    public function up()
    {
        $this->forge->addColumn('sales', [
            'client_uuid' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'offline_ref' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
        ]);
        $this->db->query('CREATE UNIQUE INDEX sales_client_uuid ON sales (client_uuid)');
    }

    public function down()
    {
        $this->forge->dropKey('sales', 'client_uuid', false);
        $this->forge->dropColumn('sales', ['client_uuid', 'offline_ref']);
    }
}
