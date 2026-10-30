<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSchema extends Migration
{
    private function id(): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
    }

    private function fk(bool $null = false): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => $null];
    }

    private function money(): array
    {
        return ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0];
    }

    private function ts(): array
    {
        return ['type' => 'DATETIME', 'null' => true];
    }

    public function up()
    {
        // ---- Lokasi (multi outlet)
        $this->forge->addField([
            'id' => $this->id(),
            'code' => ['type' => 'VARCHAR', 'constraint' => 10],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'address' => ['type' => 'TEXT', 'null' => true],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'tax_percent' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'service_percent' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'receipt_footer' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active' => ['type' => 'SMALLINT', 'default' => 1],
            'created_at' => $this->ts(), 'updated_at' => $this->ts(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('locations');

        // ---- Pengguna
        $this->forge->addField([
            'id' => $this->id(),
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'username' => ['type' => 'VARCHAR', 'constraint' => 50],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'role' => ['type' => 'VARCHAR', 'constraint' => 20], // admin|cashier|kitchen
            'location_id' => $this->fk(true),
            'is_active' => ['type' => 'SMALLINT', 'default' => 1],
            'session_token' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'device_id' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true], // perangkat yang sedang memegang sesi
            'last_seen' => $this->ts(),
            'last_login_at' => $this->ts(),
            'created_at' => $this->ts(), 'updated_at' => $this->ts(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->createTable('users');

        // ---- Kategori & Produk
        $this->forge->addField([
            'id' => $this->id(),
            'name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'created_at' => $this->ts(), 'updated_at' => $this->ts(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('categories');

        $this->forge->addField([
            'id' => $this->id(),
            'sku' => ['type' => 'VARCHAR', 'constraint' => 40],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'category_id' => $this->fk(true),
            'price' => $this->money(),
            'hpp' => $this->money(), // HPP standar (default di PO)
            'image_url' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'is_active' => ['type' => 'SMALLINT', 'default' => 1],
            'created_at' => $this->ts(), 'updated_at' => $this->ts(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('sku');
        $this->forge->createTable('products');

        // ---- Stok per lokasi + mutasi
        $this->forge->addField([
            'id' => $this->id(),
            'location_id' => $this->fk(),
            'product_id' => $this->fk(),
            'qty' => ['type' => 'INT', 'default' => 0],
            'avg_cost' => $this->money(),
            'min_qty' => ['type' => 'INT', 'default' => 5],
            'updated_at' => $this->ts(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['location_id', 'product_id']);
        $this->forge->createTable('stocks');

        $this->forge->addField([
            'id' => $this->id(),
            'location_id' => $this->fk(),
            'product_id' => $this->fk(),
            'type' => ['type' => 'VARCHAR', 'constraint' => 20], // receipt|sale|void|adjust
            'qty' => ['type' => 'INT'],
            'unit_cost' => $this->money(),
            'balance' => ['type' => 'INT', 'default' => 0],
            'ref_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'ref_id' => $this->fk(true),
            'note' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'user_id' => $this->fk(true),
            'created_at' => $this->ts(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['location_id', 'created_at']);
        $this->forge->createTable('stock_movements');

        // ---- Pesanan Pembelian (dari dapur)
        $this->forge->addField([
            'id' => $this->id(),
            'po_number' => ['type' => 'VARCHAR', 'constraint' => 40],
            'location_id' => $this->fk(),
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'sent'], // sent|partial|received|cancelled
            'notes' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'total_hpp' => $this->money(),
            'created_by' => $this->fk(true),
            'created_at' => $this->ts(), 'updated_at' => $this->ts(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('po_number');
        $this->forge->addKey(['location_id', 'status']);
        $this->forge->createTable('purchase_orders');

        $this->forge->addField([
            'id' => $this->id(),
            'po_id' => $this->fk(),
            'product_id' => $this->fk(),
            'sku' => ['type' => 'VARCHAR', 'constraint' => 40],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'hpp' => $this->money(),
            'qty' => ['type' => 'INT'],
            'qty_received' => ['type' => 'INT', 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('po_id');
        $this->forge->createTable('purchase_order_items');

        // ---- Penerimaan Pembelian (oleh kasir)
        $this->forge->addField([
            'id' => $this->id(),
            'gr_number' => ['type' => 'VARCHAR', 'constraint' => 40],
            'po_id' => $this->fk(),
            'location_id' => $this->fk(),
            'notes' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'received_by' => $this->fk(true),
            'created_at' => $this->ts(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('gr_number');
        $this->forge->createTable('goods_receipts');

        $this->forge->addField([
            'id' => $this->id(),
            'gr_id' => $this->fk(),
            'po_item_id' => $this->fk(),
            'product_id' => $this->fk(),
            'qty' => ['type' => 'INT'],
            'hpp' => $this->money(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('gr_id');
        $this->forge->createTable('goods_receipt_items');

        // ---- Metode pembayaran
        $this->forge->addField([
            'id' => $this->id(),
            'code' => ['type' => 'VARCHAR', 'constraint' => 30],
            'name' => ['type' => 'VARCHAR', 'constraint' => 60],
            'type' => ['type' => 'VARCHAR', 'constraint' => 20], // cash|card|qris|ewallet|transfer|online|other
            'sort' => ['type' => 'INT', 'default' => 0],
            'is_active' => ['type' => 'SMALLINT', 'default' => 1],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('payment_methods');

        // ---- Penjualan
        $this->forge->addField([
            'id' => $this->id(),
            'invoice_no' => ['type' => 'VARCHAR', 'constraint' => 40],
            'location_id' => $this->fk(),
            'cashier_id' => $this->fk(true),
            'order_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'dine_in'],
            'table_no' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'customer_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'subtotal' => $this->money(),
            'discount' => $this->money(),
            'tax' => $this->money(),
            'service' => $this->money(),
            'total' => $this->money(),
            'paid' => $this->money(),
            'change_amount' => $this->money(),
            'cogs' => $this->money(),
            'status' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'paid'], // paid|void
            'note' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'void_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => $this->ts(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('invoice_no');
        $this->forge->addKey(['location_id', 'created_at']);
        $this->forge->createTable('sales');

        $this->forge->addField([
            'id' => $this->id(),
            'sale_id' => $this->fk(),
            'product_id' => $this->fk(),
            'sku' => ['type' => 'VARCHAR', 'constraint' => 40],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'qty' => ['type' => 'INT'],
            'price' => $this->money(),
            'cost' => $this->money(),
            'subtotal' => $this->money(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('sale_id');
        $this->forge->createTable('sale_items');

        $this->forge->addField([
            'id' => $this->id(),
            'sale_id' => $this->fk(),
            'method_id' => $this->fk(),
            'method_name' => ['type' => 'VARCHAR', 'constraint' => 60],
            'amount' => $this->money(),
            'reference' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('sale_id');
        $this->forge->createTable('sale_payments');

        // ---- Session store (single device login butuh session di DB, Vercel stateless)
        if ($this->db->DBDriver === 'Postgre') {
            $this->db->query('CREATE TABLE IF NOT EXISTS ci_sessions (
                id VARCHAR(128) NOT NULL PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                timestamp TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
                data BYTEA NOT NULL DEFAULT \'\'
            )');
            $this->db->query('CREATE INDEX IF NOT EXISTS ci_sessions_timestamp ON ci_sessions (timestamp)');
        } else { // MySQL / MariaDB (Laragon)
            $this->db->query('CREATE TABLE IF NOT EXISTS ci_sessions (
                id VARCHAR(128) NOT NULL PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
                data BLOB NOT NULL,
                KEY ci_sessions_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down()
    {
        foreach (['sale_payments', 'sale_items', 'sales', 'payment_methods', 'goods_receipt_items', 'goods_receipts',
                  'purchase_order_items', 'purchase_orders', 'stock_movements', 'stocks', 'products',
                  'categories', 'users', 'locations'] as $t) {
            $this->forge->dropTable($t, true);
        }
        $this->db->query('DROP TABLE IF EXISTS ci_sessions');
    }
}
