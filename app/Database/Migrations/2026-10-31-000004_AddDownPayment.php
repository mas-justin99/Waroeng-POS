<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** DP reservasi: bukti DP, penandaan DP terpakai di kasir, dan metode bayar "DP Reservasi". */
class AddDownPayment extends Migration
{
    public function up()
    {
        $this->forge->addColumn('reservations', [
            'dp_proof_url' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'dp_sale_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addColumn('sale_payments', [
            'reservation_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);

        // Instalasi yang sudah berjalan: tambahkan metode DP. Instalasi baru: ditambahkan oleh DatabaseSeeder.
        $db = \Config\Database::connect();
        if ($db->table('payment_methods')->countAllResults() > 0 && $db->table('payment_methods')->where('code', 'DP')->countAllResults() === 0) {
            $max = (int) ($db->query('SELECT MAX(sort) AS m FROM payment_methods')->getRowArray()['m'] ?? 0);
            $db->table('payment_methods')->insert(['code' => 'DP', 'name' => 'DP Reservasi', 'type' => 'dp', 'sort' => $max + 1, 'is_active' => 1]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('sale_payments', 'reservation_id');
        $this->forge->dropColumn('reservations', ['dp_proof_url', 'dp_sale_id']);
        \Config\Database::connect()->table('payment_methods')->where('code', 'DP')->delete();
    }
}
