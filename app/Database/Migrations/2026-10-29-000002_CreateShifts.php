<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Buka/Tutup kasir: 2 shift per hari, modal awal & stok awal wajib diisi. */
class CreateShifts extends Migration
{
    private function id(): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
    }

    private function fk(bool $null = false): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => $null];
    }

    private function money(bool $null = false): array
    {
        return $null ? ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true] : ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0];
    }

    public function up()
    {
        $this->forge->addField([
            'id'            => $this->id(),
            'location_id'   => $this->fk(),
            'shift_no'      => ['type' => 'SMALLINT'],                       // 1 | 2
            'shift_date'    => ['type' => 'DATE'],
            'status'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'open'], // open|closed
            'cashier_id'    => $this->fk(),                                  // pembuka shift
            'opening_cash'  => $this->money(),
            'opening_note'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'opened_at'     => ['type' => 'DATETIME', 'null' => true],
            'closed_by'     => $this->fk(true),
            'closed_at'     => ['type' => 'DATETIME', 'null' => true],
            'closing_cash'  => $this->money(true),                           // hasil hitung kas fisik
            'expected_cash' => $this->money(true),                           // modal + penjualan tunai
            'cash_diff'     => $this->money(true),
            'sales_total'   => $this->money(true),
            'trx_count'     => ['type' => 'INT', 'null' => true],
            'closing_note'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['location_id', 'shift_date', 'shift_no']);
        $this->forge->addKey(['location_id', 'status']);
        $this->forge->createTable('cash_shifts');

        $this->forge->addField([
            'id'          => $this->id(),
            'shift_id'    => $this->fk(),
            'product_id'  => $this->fk(),
            'system_qty'  => ['type' => 'INT', 'default' => 0],             // stok menurut sistem saat buka
            'opening_qty' => ['type' => 'INT', 'default' => 0],             // stok awal hasil hitung kasir
            'closing_qty' => ['type' => 'INT', 'null' => true],             // stok saat tutup
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['shift_id', 'product_id']);
        $this->forge->createTable('shift_stocks');

        $this->forge->addColumn('sales', ['shift_id' => $this->fk(true)]);
        $this->db->query('CREATE INDEX sales_shift_idx ON sales (shift_id)');
    }

    public function down()
    {
        $this->db->query('DROP INDEX sales_shift_idx' . ($this->db->DBDriver === 'Postgre' ? '' : ' ON sales'));
        $this->forge->dropColumn('sales', 'shift_id');
        $this->forge->dropTable('shift_stocks', true);
        $this->forge->dropTable('cash_shifts', true);
    }
}
