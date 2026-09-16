<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * Shop stock.
 *
 * `inventory_movements` existed with nothing writing to it, so `products.stock_qty`
 * was a number nobody could account for. Every change now goes through a movement
 * that records the delta, the balance it produced, who did it and why, and the
 * product's own count is updated in the same transaction. The ledger and the
 * shelf either agree or the write is rolled back.
 *
 * An `out` that would take stock below zero is refused rather than clamped: a
 * negative shelf means the count was already wrong, and silently flooring it at
 * zero destroys the evidence.
 */
class Inventory extends BaseController
{
    private const TYPES = ['in', 'out', 'adjustment'];

    public function index(): string
    {
        $products = $this->db->table('products')
            ->select('id, sku, name, category, brand, price, cost_price, stock_qty, reorder_level, is_active')
            ->orderBy('is_active', 'DESC')->orderBy('category')->orderBy('name')
            ->get()->getResultArray();

        return $this->page('admin/inventory', [
            'title'    => 'Inventory | Bernie Pickleball',
            'products' => $products,
            'low'      => array_values(array_filter(
                $products,
                static fn ($p) => (int) $p['is_active'] === 1 && (int) $p['stock_qty'] <= (int) $p['reorder_level']
            )),
            'movements' => $this->db->table('inventory_movements im')
                ->select('im.*, p.sku, p.name AS product_name, u.first_name, u.last_name')
                ->join('products p', 'p.id = im.product_id')
                ->join('users u', 'u.id = im.user_id', 'left')
                ->orderBy('im.id', 'DESC')->limit(40)->get()->getResultArray(),
            'types'    => self::TYPES,
            'value'    => array_sum(array_map(
                static fn ($p) => (float) $p['cost_price'] * (int) $p['stock_qty'],
                $products
            )),
        ], 'layouts/admin');
    }

    public function move()
    {
        if (! $this->validate([
            'product_id' => 'required|is_natural_no_zero',
            'type'       => 'required|in_list[' . implode(',', self::TYPES) . ']',
            'qty'        => 'required|is_natural_no_zero|less_than_equal_to[10000]',
            'reason'     => 'permit_empty|max_length[160]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $product = $this->db->table('products')->where('id', (int) $this->request->getPost('product_id'))->get()->getRowArray();
        if ($product === null) {
            return redirect()->to('admin/inventory')->with('error', 'No such product.');
        }

        $type  = (string) $this->request->getPost('type');
        $qty   = (int) $this->request->getPost('qty');
        $stock = (int) $product['stock_qty'];

        // `adjustment` means "the shelf actually holds this many", so the delta
        // is whatever gets us there. The other two are deltas already.
        $balance = match ($type) {
            'in'         => $stock + $qty,
            'out'        => $stock - $qty,
            'adjustment' => $qty,
        };
        $delta = $balance - $stock;

        if ($balance < 0) {
            return redirect()->back()->withInput()->with('error', sprintf(
                '%s only has %d on the shelf. Count it and post an adjustment instead of taking out %d.',
                $product['name'],
                $stock,
                $qty
            ));
        }

        if ($delta === 0) {
            return redirect()->to('admin/inventory')->with('error', 'That leaves the count exactly where it is.');
        }

        $this->db->transBegin();

        try {
            $this->db->table('inventory_movements')->insert([
                'product_id' => (int) $product['id'],
                'type'       => $type,
                'qty'        => $delta,
                'balance'    => $balance,
                'reason'     => trim((string) $this->request->getPost('reason')) ?: null,
                'user_id'    => $this->auth->id(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->db->table('products')->where('id', (int) $product['id'])->update([
                'stock_qty'  => $balance,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('stock_write_failed');
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Stock write failed for product {id}: {msg}', ['id' => $product['id'], 'msg' => $e->getMessage()]);

            return redirect()->back()->withInput()->with('error', 'That movement did not save. The count is unchanged.');
        }

        service('audit')->log('inventory.moved', 'product', (int) $product['id'], sprintf(
            '%s %s%d, balance %d',
            $product['sku'],
            $delta > 0 ? '+' : '',
            $delta,
            $balance
        ), $this->auth->id());

        return redirect()->to('admin/inventory')->with('success', sprintf(
            '%s: %s%d, now %d on the shelf.',
            $product['name'],
            $delta > 0 ? '+' : '',
            $delta,
            $balance
        ));
    }
}
