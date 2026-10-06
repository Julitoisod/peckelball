<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * The staff roster and who is allowed to do what.
 *
 * Three guards, all of them about not handing someone the keys to the building
 * or locking the building's owner out of it:
 *
 *   1. You cannot change your own role or suspend yourself. The only person who
 *      can lock you out is somebody else, which means there is always a second
 *      person who can undo it.
 *   2. You cannot touch an account at or above your own level, so a manager
 *      cannot demote the owner.
 *   3. You cannot hand out a role above your own level, so nobody promotes
 *      themselves sideways through a colleague.
 *
 * `Auth::register()` is deliberately NOT used to create staff: it signs the new
 * account in, which would swap the admin's own session for the person they just
 * created.
 */
class Staff extends BaseController
{
    private const STATUSES = ['active', 'inactive', 'suspended'];

    public function index(): string
    {
        $me = $this->auth->user();

        return $this->page('admin/staff', [
            'title' => 'Staff | Go Paddle',
            'rows'  => $this->db->table('users u')
                ->select('u.id, u.first_name, u.last_name, u.email, u.phone, u.status,
                          u.last_login_at, u.created_at, r.name AS role_name, r.slug AS role_slug, r.level AS role_level')
                ->join('roles r', 'r.id = u.role_id')
                ->where('r.is_staff', 1)
                ->where('u.deleted_at', null)
                ->orderBy('r.level', 'DESC')->orderBy('u.first_name')
                ->get()->getResultArray(),
            'roles' => $this->db->table('roles')->where('is_staff', 1)
                ->where('level <=', (int) $me['role_level'])
                ->orderBy('level', 'DESC')->get()->getResultArray(),
            'myId'    => (int) $me['id'],
            'myLevel' => (int) $me['role_level'],
            'statuses' => self::STATUSES,
        ], 'layouts/admin');
    }

    public function create()
    {
        if (! $this->validate([
            'first_name' => 'required|max_length[60]',
            'last_name'  => 'required|max_length[60]',
            'email'      => 'required|valid_email|max_length[160]',
            'phone'      => 'permit_empty|max_length[30]',
            'role_id'    => 'required|is_natural_no_zero',
            'password'   => 'required|min_length[10]|max_length[200]',
        ], [
            'password' => ['min_length' => 'Use at least 10 characters. This account can spend money.'],
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $role = $this->assignableRole((int) $this->request->getPost('role_id'));
        if ($role === null) {
            return redirect()->back()->withInput()->with('error', 'You cannot hand out that role.');
        }

        $email = strtolower(trim((string) $this->request->getPost('email')));
        if ($this->db->table('users')->where('email', $email)->countAllResults() > 0) {
            return redirect()->back()->withInput()->with('error', $email . ' already has an account.');
        }

        $this->db->table('users')->insert([
            'role_id'       => (int) $role['id'],
            'first_name'    => trim((string) $this->request->getPost('first_name')),
            'last_name'     => trim((string) $this->request->getPost('last_name')),
            'email'         => $email,
            'phone'         => trim((string) $this->request->getPost('phone')) ?: null,
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            'skill_level'   => 'intermediate',
            'status'        => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->db->insertID();
        service('audit')->log('staff.created', 'user', $id, $email . ' as ' . $role['name'], $this->auth->id());

        return redirect()->to('admin/staff')->with('success', sprintf(
            '%s added as %s. Hand them the password you just set, they can change it once they are in.',
            trim((string) $this->request->getPost('first_name')),
            $role['name']
        ));
    }

    public function update(int $id)
    {
        $target = $this->targetFor($id);
        if (is_string($target)) {
            return redirect()->to('admin/staff')->with('error', $target);
        }

        $roleId = (int) $this->request->getPost('role_id');
        $status = (string) $this->request->getPost('status');

        if (! in_array($status, self::STATUSES, true)) {
            return redirect()->to('admin/staff')->with('error', 'That is not an account state.');
        }

        $role = $this->assignableRole($roleId);
        if ($role === null) {
            return redirect()->to('admin/staff')->with('error', 'You cannot hand out that role.');
        }

        $this->db->table('users')->where('id', $id)->update([
            'role_id'    => (int) $role['id'],
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        service('audit')->log('staff.updated', 'user', $id, sprintf(
            '%s %s is now %s and %s',
            $target['first_name'],
            $target['last_name'],
            $role['name'],
            $status
        ), $this->auth->id());

        return redirect()->to('admin/staff')->with('success', $target['first_name'] . ' updated.');
    }

    public function resetPassword(int $id)
    {
        $target = $this->targetFor($id);
        if (is_string($target)) {
            return redirect()->to('admin/staff')->with('error', $target);
        }

        if (! $this->validate(['password' => 'required|min_length[10]|max_length[200]'])) {
            return redirect()->to('admin/staff')->with('error', 'A new password needs at least 10 characters.');
        }

        $this->db->table('users')->where('id', $id)->update([
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            // A reset clears the lockout too, since that is usually why it is
            // being done at all.
            'failed_logins' => 0,
            'locked_until'  => null,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        service('audit')->log('staff.password_reset', 'user', $id, $target['email'], $this->auth->id());

        return redirect()->to('admin/staff')->with('success', $target['first_name'] . ' has a new password. Hand it over in person.');
    }

    /**
     * The account being changed, or the sentence explaining why it cannot be.
     *
     * @return array<string, mixed>|string
     */
    private function targetFor(int $id)
    {
        if ($id === (int) $this->auth->id()) {
            return 'You cannot change your own role or lock yourself out. Ask someone else to do it.';
        }

        $target = $this->db->table('users u')->select('u.*, r.level AS role_level, r.is_staff, r.name AS role_name')
            ->join('roles r', 'r.id = u.role_id')
            ->where('u.id', $id)->where('u.deleted_at', null)->get()->getRowArray();

        if ($target === null || (int) $target['is_staff'] === 0) {
            return 'That is not a staff account.';
        }

        $me = $this->auth->user();
        if ((int) $target['role_level'] >= (int) $me['role_level']) {
            return $target['first_name'] . ' is ' . $target['role_name'] . ', which is at or above your own level.';
        }

        return $target;
    }

    /** @return array<string, mixed>|null */
    private function assignableRole(int $roleId)
    {
        $me = $this->auth->user();

        return $this->db->table('roles')
            ->where('id', $roleId)
            ->where('is_staff', 1)
            ->where('level <=', (int) $me['role_level'])
            ->get()->getRowArray();
    }
}
