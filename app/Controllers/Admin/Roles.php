<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * The permission matrix.
 *
 * This is the one screen that can lock everybody out of the building, so it has
 * three guards and they are all about that:
 *
 *   1. You cannot edit a role at or above your own level, including your own.
 *      Changing the rules you are standing on is how an admin removes their own
 *      access and has nobody left to restore it.
 *   2. `roles.manage` cannot be taken off the last role that holds it. Without
 *      this, one save empties the matrix and no account can ever edit it again.
 *   3. A permission that does not exist is ignored rather than created, so a
 *      crafted post cannot invent access.
 *
 * `Auth` caches permissions in the session, so a role change reaches an already
 * signed-in user on their next request, not mid-request.
 */
class Roles extends BaseController
{
    public function index(): string
    {
        $me = $this->auth->user();

        $permissions = $this->db->table('permissions')->orderBy('module')->orderBy('slug')->get()->getResultArray();
        $grouped     = [];
        foreach ($permissions as $p) {
            $grouped[$p['module']][] = $p;
        }

        $granted = [];
        foreach ($this->db->table('role_permissions')->get()->getResultArray() as $rp) {
            $granted[(int) $rp['role_id']][(int) $rp['permission_id']] = true;
        }

        return $this->page('admin/roles', [
            'title'   => 'Roles | Bernie Pickleball',
            'roles'   => $this->db->table('roles r')
                ->select('r.*, COUNT(u.id) AS holders')
                ->join('users u', 'u.role_id = r.id AND u.deleted_at IS NULL', 'left')
                ->groupBy('r.id')->orderBy('r.level', 'DESC')->get()->getResultArray(),
            'grouped' => $grouped,
            'granted' => $granted,
            'myLevel' => (int) $me['role_level'],
            'myRole'  => (int) $me['role_id'],
            'editing' => (int) ($this->request->getGet('role') ?? 0),
        ], 'layouts/admin');
    }

    public function save(int $id)
    {
        $me   = $this->auth->user();
        $role = $this->db->table('roles')->where('id', $id)->get()->getRowArray();

        if ($role === null) {
            return redirect()->to('admin/roles')->with('error', 'No such role.');
        }
        if ((int) $role['level'] >= (int) $me['role_level']) {
            return redirect()->to('admin/roles')->with('error', sprintf(
                '%s sits at or above your own level, so its permissions are not yours to change.',
                $role['name']
            ));
        }

        // Only slugs that really exist become grants.
        $wanted = array_map('strval', (array) $this->request->getPost('perm'));
        $valid  = $this->db->table('permissions')->select('id, slug')
            ->whereIn('slug', $wanted === [] ? [''] : $wanted)->get()->getResultArray();

        $keepManage = $this->protectRolesManage($id, array_column($valid, 'slug'));
        if ($keepManage !== null) {
            return redirect()->to('admin/roles?role=' . $id)->with('error', $keepManage);
        }

        $this->db->transBegin();
        $this->db->table('role_permissions')->where('role_id', $id)->delete();
        foreach ($valid as $p) {
            $this->db->table('role_permissions')->insert([
                'role_id'       => $id,
                'permission_id' => (int) $p['id'],
            ]);
        }
        $this->db->transCommit();

        service('audit')->log('roles.updated', 'role', $id, sprintf(
            '%s now holds %d permission%s',
            $role['name'],
            count($valid),
            count($valid) === 1 ? '' : 's'
        ), $this->auth->id(), ['permissions' => array_column($valid, 'slug')]);

        return redirect()->to('admin/roles?role=' . $id)->with('success', sprintf(
            '%s updated: %d permission%s. Anyone signed in picks this up on their next click.',
            $role['name'],
            count($valid),
            count($valid) === 1 ? '' : 's'
        ));
    }

    /**
     * The sentence to refuse with, or null when the save is safe.
     *
     * @param list<string> $slugs the permissions this role is about to hold
     */
    private function protectRolesManage(int $roleId, array $slugs): ?string
    {
        if (in_array('roles.manage', $slugs, true)) {
            return null;
        }

        $others = $this->db->table('role_permissions rp')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('p.slug', 'roles.manage')
            ->where('rp.role_id !=', $roleId)
            ->countAllResults();

        if ($others > 0) {
            return null;
        }

        return 'That would take the last "manage roles" permission out of the system, '
            . 'and nobody could ever edit this screen again. Grant it somewhere else first.';
    }
}
