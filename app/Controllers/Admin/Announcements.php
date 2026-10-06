<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * Announcements. A `public` one with a live publish window is what the notice
 * strip on the landing page reads, so this screen writes straight onto the
 * front page and the audience field is the only thing keeping it internal.
 */
class Announcements extends BaseController
{
    private const AUDIENCES = ['public', 'players', 'members', 'staff', 'coaches'];

    public function index(): string
    {
        $editId = (int) ($this->request->getGet('edit') ?? 0);

        return $this->page('admin/announcements', [
            'title'   => 'Announcements | Go Paddle',
            'rows'    => $this->db->table('announcements a')
                ->select('a.*, u.first_name, u.last_name')
                ->join('users u', 'u.id = a.author_id', 'left')
                ->orderBy('a.is_pinned', 'DESC')
                ->orderBy('a.published_at', 'DESC')
                ->get()->getResultArray(),
            'editing' => $editId > 0
                ? $this->db->table('announcements')->where('id', $editId)->get()->getRowArray()
                : null,
            'audiences' => self::AUDIENCES,
        ], 'layouts/admin');
    }

    public function save()
    {
        if (! $this->validate([
            'title'    => 'required|max_length[160]',
            'body'     => 'required|max_length[5000]',
            'audience' => 'required|in_list[' . implode(',', self::AUDIENCES) . ']',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $published = $this->toDateTime((string) $this->request->getPost('published_at')) ?? date('Y-m-d H:i:s');
        $expires   = $this->toDateTime((string) $this->request->getPost('expires_at'));

        if ($this->request->getPost('published_at') !== '' && $published === null) {
            return redirect()->back()->withInput()->with('error', 'The publish date is not a date I can read.');
        }

        if ($expires !== null && $expires <= $published) {
            return redirect()->back()->withInput()->with('error', 'The expiry has to be after the publish date.');
        }

        $data = [
            'title'        => (string) $this->request->getPost('title'),
            'body'         => (string) $this->request->getPost('body'),
            'audience'     => (string) $this->request->getPost('audience'),
            'is_pinned'    => $this->request->getPost('is_pinned') ? 1 : 0,
            'published_at' => $published,
            'expires_at'   => $expires,
            'updated_at'   => date('Y-m-d H:i:s'),
        ];

        $id      = (int) ($this->request->getPost('id') ?? 0);
        $editing = $id > 0 ? $this->db->table('announcements')->where('id', $id)->get()->getRowArray() : null;

        if ($editing !== null) {
            $this->db->table('announcements')->where('id', $id)->update($data);
            service('audit')->log('announcement.updated', 'announcement', $id, $data['title'], $this->auth->id());
            $message = 'Announcement updated.';
        } else {
            $data['author_id']  = $this->auth->id();
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->table('announcements')->insert($data);
            $id = (int) $this->db->insertID();
            service('audit')->log('announcement.created', 'announcement', $id, $data['title'], $this->auth->id());
            $message = $data['audience'] === 'public'
                ? 'Posted. It is on the landing page now.'
                : 'Posted to ' . $data['audience'] . '.';
        }

        return redirect()->to('admin/announcements')->with('success', $message);
    }

    public function delete(int $id)
    {
        $row = $this->db->table('announcements')->where('id', $id)->get()->getRowArray();
        if ($row === null) {
            return redirect()->to('admin/announcements')->with('error', 'That announcement is already gone.');
        }

        $this->db->table('announcements')->where('id', $id)->delete();
        service('audit')->log('announcement.deleted', 'announcement', $id, $row['title'], $this->auth->id());

        return redirect()->to('admin/announcements')->with('success', 'Announcement deleted.');
    }

    /** `datetime-local` posts with or without seconds, and empty when cleared. */
    private function toDateTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $stamp = strtotime($value);

        return $stamp === false ? null : date('Y-m-d H:i:s', $stamp);
    }
}
