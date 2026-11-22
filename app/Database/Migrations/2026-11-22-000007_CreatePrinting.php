<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Print order bar: flag produk "bar", printer LAN per outlet, antrean cetak yang diambil agen lokal. */
class CreatePrinting extends Migration
{
    public function up()
    {
        $this->forge->addColumn('products', ['is_bar' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0]]);
        $this->forge->addColumn('locations', [
            'print_token'   => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'agent_seen_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addColumn('table_bill_items', ['printed_qty' => ['type' => 'INT', 'constraint' => 11, 'default' => 0]]);

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'location_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 60],
            'host'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'port'        => ['type' => 'INT', 'constraint' => 11, 'default' => 9100],
            'cols'        => ['type' => 'INT', 'constraint' => 11, 'default' => 32],
            'cut'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('location_id');
        $this->forge->createTable('printers');

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'location_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'printer_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'kind'        => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'order'], // order|cancel|reprint|test
            'ref'         => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'payload'     => ['type' => 'TEXT'], // base64 byte ESC/POS siap kirim
            'status'      => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'pending'], // pending|printing|done|failed
            'attempts'    => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'error'       => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'claimed_at'  => ['type' => 'DATETIME', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'done_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['location_id', 'status']);
        $this->forge->createTable('print_jobs');
    }

    public function down()
    {
        $this->forge->dropTable('print_jobs', true);
        $this->forge->dropTable('printers', true);
        $this->forge->dropColumn('table_bill_items', 'printed_qty');
        $this->forge->dropColumn('locations', ['print_token', 'agent_seen_at']);
        $this->forge->dropColumn('products', 'is_bar');
    }
}
