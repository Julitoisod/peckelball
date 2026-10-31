<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * What a player hands over to prove a GCash payment: the reference on the
 * receipt and a screenshot of it. An online booking stays pending until the
 * desk has checked both against the money that actually arrived.
 *
 * `payment_proof` is a file name inside writable/uploads/payment-proofs, which
 * is outside the web root. Admin\Payments::proof() is the only way to read one.
 */
class BookingPaymentProof extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('bookings', [
            'gcash_reference' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'payment_status'],
            'payment_proof'   => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'gcash_reference'],
        ]);

        $this->db->query('CREATE INDEX bookings_gcash_reference ON bookings (gcash_reference)');
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX bookings_gcash_reference ON bookings');
        $this->forge->dropColumn('bookings', ['gcash_reference', 'payment_proof']);
    }
}
