<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * The rental catalogue. `available_qty` is what the booking form offers, so it
 * is kept at or below `total_qty` here rather than trusting the form: a kit
 * that can be rented more often than it exists turns into a queue at the desk.
 */
class Equipment extends BaseController
{
    private const CATEGORIES = ['paddle', 'ball', 'net', 'shoes', 'apparel', 'other'];
    private const CONDITIONS = ['new', 'good', 'fair', 'retired'];

    public function index(): string
    {
        $editId = (int) ($this->request->getGet('edit') ?? 0);

        // What is out on court right now, so the counts can be read against it.
        $onLoan = array_column(
            $this->db->table('booking_equipment be')
                ->select('be.equipment_id, SUM(be.qty) AS qty')
                ->join('bookings b', 'b.id = be.booking_id')
                ->where('b.booking_date', business_date())
                ->whereIn('b.status', ['confirmed', 'checked_in'])
                ->groupBy('be.equipment_id')->get()->getResultArray(),
            'qty',
            'equipment_id'
        );

        return $this->page('admin/equipment', [
            'title'      => 'Equipment | Go Paddle',
            'rows'       => $this->db->table('equipment')->orderBy('is_active', 'DESC')->orderBy('category')->orderBy('code')->get()->getResultArray(),
            'onLoan'     => $onLoan,
            'editing'    => $editId > 0
                ? $this->db->table('equipment')->where('id', $editId)->get()->getRowArray()
                : null,
            'categories' => self::CATEGORIES,
            'conditions' => self::CONDITIONS,
        ], 'layouts/admin');
    }

    public function save()
    {
        if (! $this->validate([
            'code'                => 'required|max_length[20]|regex_match[/^[A-Za-z0-9_-]+$/]',
            'name'                => 'required|max_length[120]',
            'category'            => 'required|in_list[' . implode(',', self::CATEGORIES) . ']',
            'condition_grade'     => 'required|in_list[' . implode(',', self::CONDITIONS) . ']',
            'description'         => 'permit_empty|max_length[500]',
            'rental_rate_hourly'  => 'required|numeric|greater_than_equal_to[0]',
            'deposit'             => 'permit_empty|numeric|greater_than_equal_to[0]',
            'total_qty'           => 'required|is_natural',
            'available_qty'       => 'required|is_natural',
        ], [
            'code' => ['regex_match' => 'A code can only hold letters, numbers, dashes and underscores.'],
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $id    = (int) ($this->request->getPost('id') ?? 0);
        $code  = strtoupper(trim((string) $this->request->getPost('code')));
        $total = (int) $this->request->getPost('total_qty');
        $free  = (int) $this->request->getPost('available_qty');

        if ($free > $total) {
            return redirect()->back()->withInput()->with('error', 'Available cannot be higher than the total owned.');
        }

        $clash = $this->db->table('equipment')->where('code', $code);
        if ($id > 0) {
            $clash->where('id !=', $id);
        }
        if ($clash->countAllResults() > 0) {
            return redirect()->back()->withInput()->with('error', $code . ' is already in use.');
        }

        $data = [
            'code'               => $code,
            'name'               => (string) $this->request->getPost('name'),
            'category'           => (string) $this->request->getPost('category'),
            'description'        => (string) $this->request->getPost('description'),
            'rental_rate_hourly' => (float) $this->request->getPost('rental_rate_hourly'),
            'deposit'            => (float) ($this->request->getPost('deposit') ?: 0),
            'total_qty'          => $total,
            'available_qty'      => $free,
            'condition_grade'    => (string) $this->request->getPost('condition_grade'),
            'is_active'          => $this->request->getPost('is_active') ? 1 : 0,
            'updated_at'         => date('Y-m-d H:i:s'),
        ];

        $editing = $id > 0 ? $this->db->table('equipment')->where('id', $id)->get()->getRowArray() : null;

        if ($editing !== null) {
            $this->db->table('equipment')->where('id', $id)->update($data);
            service('audit')->log('equipment.updated', 'equipment', $id, $code . ' ' . $data['name'], $this->auth->id(), $data);
            $message = $data['name'] . ' updated.';
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->table('equipment')->insert($data);
            service('audit')->log('equipment.created', 'equipment', (int) $this->db->insertID(), $code . ' ' . $data['name'], $this->auth->id(), $data);
            $message = $data['name'] . ' added to the catalogue.';
        }

        return redirect()->to('admin/equipment')->with('success', $message);
    }

    public function toggle(int $id)
    {
        $row = $this->db->table('equipment')->where('id', $id)->get()->getRowArray();
        if ($row === null) {
            return redirect()->to('admin/equipment')->with('error', 'No such item.');
        }

        $next = $row['is_active'] ? 0 : 1;
        $this->db->table('equipment')->where('id', $id)->update([
            'is_active'  => $next,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        service('audit')->log($next ? 'equipment.enabled' : 'equipment.disabled', 'equipment', $id, $row['code'], $this->auth->id());

        return redirect()->to('admin/equipment')->with(
            'success',
            $row['name'] . ($next ? ' is rentable again.' : ' is off the rental list.')
        );
    }
}
