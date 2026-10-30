<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Reservasi meja + pengingat. */
class CreateReservations extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'location_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'customer_name'  => ['type' => 'VARCHAR', 'constraint' => 100],
            'phone'          => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'reserved_at'    => ['type' => 'DATETIME'],
            'pax'            => ['type' => 'INT', 'default' => 2],
            'table_no'       => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'dp_amount'      => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'note'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'booked'], // booked|confirmed|arrived|cancelled|no_show
            'remind_minutes' => ['type' => 'INT', 'default' => 60],                                // pengingat muncul X menit sebelum jam reservasi
            'ack_at'         => ['type' => 'DATETIME', 'null' => true],                            // pengingat sudah ditandai "sudah diingatkan"
            'created_by'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['location_id', 'reserved_at']);
        $this->forge->createTable('reservations');
    }

    public function down()
    {
        $this->forge->dropTable('reservations', true);
    }
}
