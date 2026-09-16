<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Session\Session;
use Config\Database;

/**
 * Session authentication and permission checks.
 *
 * Passwords are password_hash()/password_verify() only. Repeated failures
 * lock the account for a window rather than leaking which half of the pair
 * was wrong — the error string is deliberately identical for "no such user"
 * and "wrong password".
 */
class Auth
{
    private const MAX_ATTEMPTS  = 6;
    private const LOCK_MINUTES  = 15;
    private const SESSION_KEY   = 'bernie_user_id';
    private const PERM_KEY      = 'bernie_permissions';

    private BaseConnection $db;
    private Session $session;
    /** @var array<string, mixed>|null */
    private ?array $user = null;
    private bool $loaded = false;

    public function __construct()
    {
        $this->db      = Database::connect();
        $this->session = session();
    }

    /** @return array{ok:bool, error?:string} */
    public function attempt(string $email, string $password, bool $remember = false): array
    {
        $generic = 'Email or password is incorrect.';
        $email   = strtolower(trim($email));

        $user = $this->db->table('users')->where('email', $email)->where('deleted_at', null)->get()->getRowArray();

        if ($user === null) {
            // Constant-ish work either way so timing does not confirm the email.
            password_verify($password, '$2y$10$usesomesillystringfoeueoeueoeueoeuOZ8fZ8ZC5wOZ1LqQmT9NfKG5PU5e');

            return ['ok' => false, 'error' => $generic];
        }

        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
            $mins = max(1, (int) ceil((strtotime($user['locked_until']) - time()) / 60));

            return ['ok' => false, 'error' => "Too many attempts. Try again in {$mins} minute" . ($mins === 1 ? '' : 's') . '.'];
        }

        if (! password_verify($password, $user['password_hash'])) {
            $failed = (int) $user['failed_logins'] + 1;
            $this->db->table('users')->where('id', $user['id'])->update([
                'failed_logins' => $failed,
                'locked_until'  => $failed >= self::MAX_ATTEMPTS ? date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60) : null,
            ]);

            return ['ok' => false, 'error' => $generic];
        }

        if ($user['status'] !== 'active') {
            return ['ok' => false, 'error' => 'That account is not active. Talk to the front desk.'];
        }

        // Rehash if PHP's default cost has moved since this hash was written.
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->table('users')->where('id', $user['id'])->update([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        }

        $this->db->table('users')->where('id', $user['id'])->update([
            'failed_logins' => 0,
            'locked_until'  => null,
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => service('request')->getIPAddress(),
        ]);

        // New session id on privilege change — blocks session fixation.
        $this->session->regenerate(true);
        $this->session->set(self::SESSION_KEY, (int) $user['id']);
        $this->session->remove(self::PERM_KEY);
        $this->user   = null;
        $this->loaded = false;

        service('audit')->log('auth.login', 'user', (int) $user['id'], $user['email'], (int) $user['id']);

        return ['ok' => true];
    }

    /** @return array{ok:bool, error?:string, id?:int} */
    public function register(array $data): array
    {
        $email = strtolower(trim($data['email']));

        if ($this->db->table('users')->where('email', $email)->countAllResults() > 0) {
            return ['ok' => false, 'error' => 'That email already has an account. Sign in instead.'];
        }

        $roleId = (int) $this->db->table('roles')->select('id')->where('slug', 'player')->get()->getRow('id');

        $this->db->table('users')->insert([
            'role_id'       => $roleId,
            'first_name'    => trim($data['first_name']),
            'last_name'     => trim($data['last_name']),
            'email'         => $email,
            'phone'         => trim($data['phone'] ?? '') ?: null,
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'skill_level'   => in_array($data['skill_level'] ?? '', ['beginner', 'intermediate', 'advanced', 'pro'], true) ? $data['skill_level'] : 'beginner',
            'status'        => 'active',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->db->insertID();

        $this->session->regenerate(true);
        $this->session->set(self::SESSION_KEY, $id);
        $this->session->remove(self::PERM_KEY);

        service('audit')->log('auth.registered', 'user', $id, $email, $id);
        service('bernieNotify')->push($id, 'system', 'Welcome to Bernie Pickleball', 'Your account is live. Book a court whenever you are ready.', '/booking');

        return ['ok' => true, 'id' => $id];
    }

    public function logout(): void
    {
        $id = $this->id();
        if ($id !== null) {
            service('audit')->log('auth.logout', 'user', $id, null, $id);
        }

        $this->session->remove([self::SESSION_KEY, self::PERM_KEY]);
        $this->session->destroy();
        $this->user   = null;
        $this->loaded = false;
    }

    public function id(): ?int
    {
        $id = $this->session->get(self::SESSION_KEY);

        return $id === null ? null : (int) $id;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;

        $id = $this->id();
        if ($id === null) {
            return $this->user = null;
        }

        $row = $this->db->table('users')
            ->select('users.*, roles.slug AS role_slug, roles.name AS role_name, roles.level AS role_level, roles.is_staff')
            ->join('roles', 'roles.id = users.role_id')
            ->where('users.id', $id)
            ->where('users.deleted_at', null)
            ->get()->getRowArray();

        // Deleted or suspended mid-session: drop the session rather than serve it.
        if ($row === null || $row['status'] !== 'active') {
            $this->session->remove([self::SESSION_KEY, self::PERM_KEY]);

            return $this->user = null;
        }

        return $this->user = $row;
    }

    public function name(): string
    {
        $u = $this->user();

        return $u === null ? 'Guest' : trim($u['first_name'] . ' ' . $u['last_name']);
    }

    public function isStaff(): bool
    {
        $u = $this->user();

        return $u !== null && (bool) $u['is_staff'];
    }

    public function hasRole(string ...$slugs): bool
    {
        $u = $this->user();

        return $u !== null && in_array($u['role_slug'], $slugs, true);
    }

    /** @return list<string> */
    public function permissions(): array
    {
        $cached = $this->session->get(self::PERM_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $u = $this->user();
        if ($u === null) {
            return [];
        }

        $perms = array_column(
            $this->db->table('role_permissions rp')
                ->select('p.slug')
                ->join('permissions p', 'p.id = rp.permission_id')
                ->where('rp.role_id', (int) $u['role_id'])
                ->get()->getResultArray(),
            'slug'
        );

        $this->session->set(self::PERM_KEY, $perms);

        return $perms;
    }

    public function can(string ...$permissions): bool
    {
        $held = $this->permissions();
        foreach ($permissions as $p) {
            if (in_array($p, $held, true)) {
                return true;
            }
        }

        return false;
    }
}
