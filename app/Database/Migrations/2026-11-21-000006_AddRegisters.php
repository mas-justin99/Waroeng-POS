<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Beberapa kasir (register) dalam satu outlet: tiap kasir punya shift, laci, dan penutupan sendiri. */
class AddRegisters extends Migration
{
    public function up()
    {
        $this->forge->addColumn('cash_shifts', [
            'register_no' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
        ]);
        $this->forge->addColumn('locations', [
            'register_count' => ['type' => 'INT', 'constraint' => 11, 'default' => 2],
        ]);
        // satu shift per (lokasi, tanggal, shift, kasir/register)
        $this->forge->dropKey('cash_shifts', 'cash_shifts_location_id_shift_date_shift_no');
        $this->db->query('CREATE UNIQUE INDEX cash_shifts_loc_date_shift_reg ON cash_shifts (location_id, shift_date, shift_no, register_no)');
    }

    public function down()
    {
        $this->forge->dropKey('cash_shifts', 'cash_shifts_loc_date_shift_reg');
        $this->forge->dropColumn('cash_shifts', 'register_no');
        $this->forge->dropColumn('locations', 'register_count');
        $this->db->query('CREATE UNIQUE INDEX cash_shifts_location_id_shift_date_shift_no ON cash_shifts (location_id, shift_date, shift_no)');
    }
}
