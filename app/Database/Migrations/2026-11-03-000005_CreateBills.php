<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Bill per meja: pesanan terbuka (belum dibayar) yang bisa ditambah, dicetak sebagai bill sementara, lalu dibayar. */
class CreateBills extends Migration
{
    private function id(): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
    }

    public function up()
    {
        $this->forge->addField([
            'id'             => $this->id(),
            'location_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'table_no'       => ['type' => 'VARCHAR', 'constraint' => 20],
            'customer_name'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'order_type'     => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'dine_in'],
            'discount_type'  => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'nominal'],
            'discount_value' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'open'], // open|paid|cancelled
            'opened_by'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'sale_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'opened_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'closed_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['location_id', 'status', 'table_no']);
        $this->forge->createTable('table_bills');

        $this->forge->addField([
            'id'         => $this->id(),
            'bill_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'product_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'qty'        => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['bill_id', 'product_id']);
        $this->forge->createTable('table_bill_items');
    }

    public function down()
    {
        $this->forge->dropTable('table_bill_items', true);
        $this->forge->dropTable('table_bills', true);
    }
}
