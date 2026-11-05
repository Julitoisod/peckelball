<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * Coach profiles and the hours they teach.
 *
 * A coach is a user with a profile attached, not a separate kind of person, so
 * creating one here links an existing account rather than inventing a second
 * identity for someone who already books courts.
 *
 * `rating` and `sessions_taught` are deliberately not editable: they are
 * earned numbers that the public profile page presents as fact.
 */
class Coaches extends BaseController
{
    private const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    public function index(): string
    {
        $editId = (int) ($this->request->getGet('edit') ?? 0);

        $coaches = $this->db->table('coaches c')
            ->select('c.*, u.first_name, u.last_name, u.email, u.phone,
                      COUNT(DISTINCT cs.id) AS booked_sessions')
            ->join('users u', 'u.id = c.user_id')
            ->join('coaching_sessions cs', 'cs.coach_id = c.id AND cs.status <> "cancelled"', 'left')
            ->where('c.deleted_at', null)
            ->groupBy('c.id')
            ->orderBy('c.sort_order')->get()->getResultArray();

        $editing = null;
        if ($editId > 0) {
            foreach ($coaches as $c) {
                if ((int) $c['id'] === $editId) {
                    $editing = $c;
                    $editing['availability'] = $this->db->table('coach_availability')
                        ->where('coach_id', $editId)->orderBy('day_of_week')->orderBy('starts_at')
                        ->get()->getResultArray();
                    break;
                }
            }
        }

        return $this->page('admin/coaches', [
            'title'    => 'Coaches | Go Paddle',
            'coaches'  => $coaches,
            'editing'  => $editing,
            'days'     => self::DAYS,
            // Anyone with an account can be given a coach profile, minus those
            // who already have one.
            'linkable' => $this->db->table('users u')
                ->select('u.id, u.first_name, u.last_name, u.email')
                ->join('coaches c', 'c.user_id = u.id AND c.deleted_at IS NULL', 'left')
                ->where('u.deleted_at', null)
                ->where('u.status', 'active')
                ->where('c.id', null)
                ->orderBy('u.first_name')->get()->getResultArray(),
        ], 'layouts/admin');
    }

    public function save()
    {
        if (! $this->validate([
            'headline'         => 'permit_empty|max_length[160]',
            'bio'              => 'permit_empty|max_length[3000]',
            'specialties'      => 'permit_empty|max_length[500]',
            'certifications'   => 'permit_empty|max_length[500]',
            'years_experience' => 'permit_empty|is_natural|less_than_equal_to[70]',
            'hourly_rate'      => 'required|numeric|greater_than_equal_to[0]',
            'group_rate'       => 'permit_empty|numeric|greater_than_equal_to[0]',
            'sort_order'       => 'permit_empty|is_natural',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $id   = (int) ($this->request->getPost('id') ?? 0);
        $data = [
            'headline'         => trim((string) $this->request->getPost('headline')) ?: null,
            'bio'              => trim((string) $this->request->getPost('bio')) ?: null,
            'specialties'      => trim((string) $this->request->getPost('specialties')) ?: null,
            'certifications'   => trim((string) $this->request->getPost('certifications')) ?: null,
            'years_experience' => (int) $this->request->getPost('years_experience'),
            'hourly_rate'      => (float) $this->request->getPost('hourly_rate'),
            'group_rate'       => (float) ($this->request->getPost('group_rate') ?: 0),
            'is_accepting'     => $this->request->getPost('is_accepting') ? 1 : 0,
            'sort_order'       => (int) ($this->request->getPost('sort_order') ?: 0),
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            $existing = $this->db->table('coaches')->where('id', $id)->where('deleted_at', null)->get()->getRowArray();
            if ($existing === null) {
                return redirect()->to('admin/coaches')->with('error', 'No such coach.');
            }

            $this->db->table('coaches')->where('id', $id)->update($data);
            service('audit')->log('coach.updated', 'coach', $id, (string) $data['headline'], $this->auth->id(), $data);

            return redirect()->to('admin/coaches?edit=' . $id)->with('success', 'Coach profile updated.');
        }

        $userId = (int) $this->request->getPost('user_id');
        $user   = $this->db->table('users')->where('id', $userId)->where('deleted_at', null)->get()->getRowArray();
        if ($user === null) {
            return redirect()->back()->withInput()->with('error', 'Pick the account this coach signs in with.');
        }
        if ($this->db->table('coaches')->where('user_id', $userId)->where('deleted_at', null)->countAllResults() > 0) {
            return redirect()->back()->withInput()->with('error', $user['first_name'] . ' already has a coach profile.');
        }

        $data['user_id']    = $userId;
        $data['rating']     = 0;
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->table('coaches')->insert($data);
        $newId = (int) $this->db->insertID();

        service('audit')->log('coach.created', 'coach', $newId, $user['first_name'] . ' ' . $user['last_name'], $this->auth->id());

        return redirect()->to('admin/coaches?edit=' . $newId)->with('success', $user['first_name'] . ' is now a coach. Add the hours they teach.');
    }

    public function toggle(int $id)
    {
        $coach = $this->db->table('coaches c')->select('c.*, u.first_name')
            ->join('users u', 'u.id = c.user_id')
            ->where('c.id', $id)->where('c.deleted_at', null)->get()->getRowArray();

        if ($coach === null) {
            return redirect()->to('admin/coaches')->with('error', 'No such coach.');
        }

        $next = $coach['is_accepting'] ? 0 : 1;
        $this->db->table('coaches')->where('id', $id)->update([
            'is_accepting' => $next,
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
        service('audit')->log($next ? 'coach.accepting' : 'coach.paused', 'coach', $id, $coach['first_name'], $this->auth->id());

        return redirect()->to('admin/coaches')->with('success', $coach['first_name'] . ($next
            ? ' is taking sessions again.'
            : ' is off the coaching page. Sessions already booked are untouched.'));
    }

    public function addSlot(int $id)
    {
        if (! $this->validate([
            'day_of_week' => 'required|is_natural|less_than[7]',
            'starts_at'   => 'required|regex_match[/^([01]\d|2[0-3]):[0-5]\d$/]',
            'ends_at'     => 'required|regex_match[/^([01]\d|2[0-3]):[0-5]\d$/]',
        ])) {
            return redirect()->to('admin/coaches?edit=' . $id)->with('error', 'A window needs a day and two times.');
        }

        $start = (string) $this->request->getPost('starts_at');
        $end   = (string) $this->request->getPost('ends_at');
        if ($end <= $start) {
            return redirect()->to('admin/coaches?edit=' . $id)->with('error', 'The window has to end after it starts.');
        }

        if ($this->db->table('coaches')->where('id', $id)->where('deleted_at', null)->countAllResults() === 0) {
            return redirect()->to('admin/coaches')->with('error', 'No such coach.');
        }

        $this->db->table('coach_availability')->insert([
            'coach_id'    => $id,
            'day_of_week' => (int) $this->request->getPost('day_of_week'),
            'starts_at'   => $start . ':00',
            'ends_at'     => $end . ':00',
        ]);
        service('audit')->log('coach.availability', 'coach', $id, self::DAYS[(int) $this->request->getPost('day_of_week')] . ' ' . clock($start) . ' to ' . clock($end), $this->auth->id());

        return redirect()->to('admin/coaches?edit=' . $id)->with('success', 'Window added.');
    }

    public function dropSlot(int $id, int $slotId)
    {
        $this->db->table('coach_availability')->where('id', $slotId)->where('coach_id', $id)->delete();
        service('audit')->log('coach.availability', 'coach', $id, 'window removed', $this->auth->id());

        return redirect()->to('admin/coaches?edit=' . $id)->with('success', 'Window removed.');
    }
}
