<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Riwayat perubahan transaksi penjualan (audit). */
class SaleEdits extends Migration
{
    public function up()
    {
        $this->forge->addColumn('sales', ['edit_count' => ['type' => 'INT', 'constraint' => 11, 'default' => 0]]);
        $this->forge->addField([
            'id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'sale_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'reason'    => ['type' => 'VARCHAR', 'constraint' => 200],
            'old_total' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'new_total' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'diff'      => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'detail'    => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('sale_id');
        $this->forge->createTable('sale_edits');
    }

    public function down()
    {
        $this->forge->dropTable('sale_edits', true);
        $this->forge->dropColumn('sales', 'edit_count');
    }
}
