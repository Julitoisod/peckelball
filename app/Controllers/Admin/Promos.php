<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * Promo codes. Money leaves the till through this table, so a code is never
 * deleted once it has been used: it is deactivated, and `used_count` stays as
 * the record of what it cost.
 */
class Promos extends BaseController
{
    public function index(): string
    {
        $editId = (int) ($this->request->getGet('edit') ?? 0);

        return $this->page('admin/promos', [
            'title'   => 'Promo codes | Go Paddle',
            'rows'    => $this->db->table('promo_codes')->orderBy('is_active', 'DESC')->orderBy('code')->get()->getResultArray(),
            'editing' => $editId > 0
                ? $this->db->table('promo_codes')->where('id', $editId)->get()->getRowArray()
                : null,
        ], 'layouts/admin');
    }

    public function save()
    {
        if (! $this->validate([
            'code'           => 'required|max_length[32]|regex_match[/^[A-Za-z0-9_-]+$/]',
            'description'    => 'permit_empty|max_length[255]',
            'discount_type'  => 'required|in_list[percent,fixed]',
            'discount_value' => 'required|numeric|greater_than[0]',
            'min_amount'     => 'permit_empty|numeric|greater_than_equal_to[0]',
            'max_uses'       => 'permit_empty|is_natural',
        ], [
            'code' => ['regex_match' => 'A code can only hold letters, numbers, dashes and underscores.'],
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $id    = (int) ($this->request->getPost('id') ?? 0);
        $code  = strtoupper(trim((string) $this->request->getPost('code')));
        $type  = (string) $this->request->getPost('discount_type');
        $value = (float) $this->request->getPost('discount_value');

        if ($type === 'percent' && $value > 100) {
            return redirect()->back()->withInput()->with('error', 'A percentage discount cannot go over 100.');
        }

        // Two codes that differ only in case are the same code at the desk.
        $clash = $this->db->table('promo_codes')->where('code', $code);
        if ($id > 0) {
            $clash->where('id !=', $id);
        }
        if ($clash->countAllResults() > 0) {
            return redirect()->back()->withInput()->with('error', $code . ' already exists.');
        }

        $starts = $this->toDate((string) $this->request->getPost('starts_on'));
        $ends   = $this->toDate((string) $this->request->getPost('ends_on'));

        if ($starts !== null && $ends !== null && $ends < $starts) {
            return redirect()->back()->withInput()->with('error', 'The end date is before the start date.');
        }

        $data = [
            'code'           => $code,
            'description'    => (string) $this->request->getPost('description'),
            'discount_type'  => $type,
            'discount_value' => $value,
            'min_amount'     => (float) ($this->request->getPost('min_amount') ?: 0),
            'max_uses'       => (int) ($this->request->getPost('max_uses') ?: 0),
            'starts_on'      => $starts,
            'ends_on'        => $ends,
            'is_active'      => $this->request->getPost('is_active') ? 1 : 0,
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        $editing = $id > 0 ? $this->db->table('promo_codes')->where('id', $id)->get()->getRowArray() : null;

        if ($editing !== null) {
            $this->db->table('promo_codes')->where('id', $id)->update($data);
            service('audit')->log('promo.updated', 'promo_code', $id, $code, $this->auth->id(), $data);
            $message = $code . ' updated.';
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->table('promo_codes')->insert($data);
            service('audit')->log('promo.created', 'promo_code', (int) $this->db->insertID(), $code, $this->auth->id(), $data);
            $message = $code . ' created.';
        }

        return redirect()->to('admin/promos')->with('success', $message);
    }

    public function toggle(int $id)
    {
        $row = $this->db->table('promo_codes')->where('id', $id)->get()->getRowArray();
        if ($row === null) {
            return redirect()->to('admin/promos')->with('error', 'No such code.');
        }

        $next = $row['is_active'] ? 0 : 1;
        $this->db->table('promo_codes')->where('id', $id)->update([
            'is_active'  => $next,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        service('audit')->log($next ? 'promo.enabled' : 'promo.disabled', 'promo_code', $id, $row['code'], $this->auth->id());

        return redirect()->to('admin/promos')->with('success', $row['code'] . ($next ? ' is live.' : ' is off.'));
    }

    private function toDate(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || strtotime($value) === false ? null : date('Y-m-d', strtotime($value));
    }
}
