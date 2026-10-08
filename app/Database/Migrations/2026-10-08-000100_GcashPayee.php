<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Who a GCash payment goes to, so the desk can change it without a deploy.
 *
 * The QR itself is a file (`assets/img/gcash-qr.jpg`) because that is what
 * GCash hands you; these two rows are the name and number printed beside it,
 * and the booking screens leave them out while they are blank.
 */
class GcashPayee extends Migration
{
    private const ROWS = [
        ['payments.gcash_name', 'GCash account name'],
        ['payments.gcash_number', 'GCash number'],
    ];

    public function up(): void
    {
        $now  = date('Y-m-d H:i:s');
        $rows = [];

        foreach (self::ROWS as [$key, $label]) {
            if ($this->db->table('settings')->where('key', $key)->countAllResults() > 0) {
                continue;
            }

            $rows[] = [
                'key'        => $key,
                'value'      => '',
                'type'       => 'string',
                'group'      => 'pricing',
                'label'      => $label,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            $this->db->table('settings')->insertBatch($rows);
        }
    }

    public function down(): void
    {
        $this->db->table('settings')
            ->whereIn('key', array_column(self::ROWS, 0))
            ->delete();
    }
}
