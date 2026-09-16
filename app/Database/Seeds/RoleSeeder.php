<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * The seven roles the facility actually runs on, plus the permission grid
 * behind them. Permissions are module-scoped slugs; `AuthService::can()`
 * resolves them once per request and caches on the session.
 */
class RoleSeeder extends Seeder
{
    /** @var array<string, array{name:string, level:int, staff:bool, desc:string}> */
    private const ROLES = [
        'super_admin'  => ['name' => 'Super Administrator', 'level' => 100, 'staff' => true,  'desc' => 'Full system access including roles, settings and audit trail.'],
        'owner'        => ['name' => 'Business Owner',      'level' => 90,  'staff' => true,  'desc' => 'Owns the business. Full operational and financial access.'],
        'manager'      => ['name' => 'Manager',             'level' => 70,  'staff' => true,  'desc' => 'Runs day-to-day operations, staff and programs.'],
        'receptionist' => ['name' => 'Receptionist',        'level' => 40,  'staff' => true,  'desc' => 'Front desk: walk-ins, check-in, payments, rentals.'],
        'coach'        => ['name' => 'Coach',               'level' => 40,  'staff' => true,  'desc' => 'Manages their own coaching roster and session notes.'],
        'maintenance'  => ['name' => 'Maintenance Staff',   'level' => 30,  'staff' => true,  'desc' => 'Court maintenance schedule and status reporting.'],
        'player'       => ['name' => 'Player',              'level' => 10,  'staff' => false, 'desc' => 'Books courts, joins tournaments, buys memberships.'],
    ];

    /** @var array<string, array{0:string, 1:string}> slug => [module, label] */
    private const PERMISSIONS = [
        'dashboard.view'       => ['dashboard',    'View dashboard overview'],
        'courts.view'          => ['courts',       'View courts'],
        'courts.manage'        => ['courts',       'Create, edit and retire courts'],
        'bookings.view'        => ['bookings',     'View booking calendar and reservations'],
        'bookings.manage'      => ['bookings',     'Create, reschedule and cancel bookings'],
        'bookings.walkin'      => ['bookings',     'Record walk-in reservations'],
        'checkin.scan'         => ['bookings',     'Scan QR codes and check players in'],
        'customers.manage'     => ['customers',    'Manage customer records'],
        'memberships.manage'   => ['memberships',  'Manage membership plans and subscriptions'],
        'coaches.manage'       => ['coaches',      'Manage the coaching roster'],
        'sessions.own'         => ['coaches',      'Manage own coaching sessions'],
        'staff.manage'         => ['staff',        'Manage staff accounts and shifts'],
        'tournaments.manage'   => ['tournaments',  'Manage tournaments, brackets and registration'],
        'tournaments.score'    => ['tournaments',  'Enter live scores'],
        'equipment.manage'     => ['equipment',    'Manage rental equipment'],
        'products.manage'      => ['products',     'Manage shop products and inventory'],
        'payments.view'        => ['payments',     'View payments'],
        'payments.manage'      => ['payments',     'Record, void and refund payments'],
        'promos.manage'        => ['promos',       'Manage promo codes and discounts'],
        'maintenance.manage'   => ['maintenance',  'Schedule and complete court maintenance'],
        'announcements.manage' => ['content',      'Publish announcements and notifications'],
        'reports.sales'        => ['reports',      'View sales reports'],
        'reports.bookings'     => ['reports',      'View booking reports'],
        'reports.analytics'    => ['reports',      'View peak-hour analytics'],
        'reports.customers'    => ['reports',      'View customer activity reports'],
        'roles.manage'         => ['system',       'Manage roles and permissions'],
        'settings.manage'      => ['system',       'Change system settings'],
        'audit.view'           => ['system',       'Read the audit log'],
    ];

    /** @var array<string, list<string>> role slug => permission slugs ('*' = everything) */
    private const GRANTS = [
        'super_admin'  => ['*'],
        'owner'        => ['*'],
        'manager'      => [
            'dashboard.view', 'courts.view', 'courts.manage', 'bookings.view', 'bookings.manage',
            'bookings.walkin', 'checkin.scan', 'customers.manage', 'memberships.manage',
            'coaches.manage', 'staff.manage', 'tournaments.manage', 'tournaments.score',
            'equipment.manage', 'products.manage', 'payments.view', 'payments.manage',
            'promos.manage', 'maintenance.manage', 'announcements.manage',
            'reports.sales', 'reports.bookings', 'reports.analytics', 'reports.customers',
            // Managers supervise a desk that takes and refunds cash. The audit
            // log is the check on that, so they can read it.
            'audit.view',
        ],
        'receptionist' => [
            'dashboard.view', 'courts.view', 'bookings.view', 'bookings.manage', 'bookings.walkin',
            'checkin.scan', 'customers.manage', 'equipment.manage', 'products.manage',
            'payments.view', 'payments.manage', 'memberships.manage',
        ],
        // Deliberately without `bookings.view`: that is the whole facility's
        // booking calendar, with every customer's name, phone number and price.
        // A coach gets their own diary through `sessions.own` instead.
        'coach'        => ['dashboard.view', 'courts.view', 'sessions.own'],
        'maintenance'  => ['dashboard.view', 'courts.view', 'maintenance.manage'],
        'player'       => [],
    ];

    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $roleRows = [];
        foreach (self::ROLES as $slug => $r) {
            $roleRows[] = [
                'slug'        => $slug,
                'name'        => $r['name'],
                'description' => $r['desc'],
                'level'       => $r['level'],
                'is_staff'    => $r['staff'] ? 1 : 0,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }
        $this->db->table('roles')->insertBatch($roleRows);

        $permRows = [];
        foreach (self::PERMISSIONS as $slug => [$module, $label]) {
            $permRows[] = ['slug' => $slug, 'name' => $label, 'module' => $module];
        }
        $this->db->table('permissions')->insertBatch($permRows);

        $roleIds = array_column($this->db->table('roles')->get()->getResultArray(), 'id', 'slug');
        $permIds = array_column($this->db->table('permissions')->get()->getResultArray(), 'id', 'slug');

        $grantRows = [];
        foreach (self::GRANTS as $roleSlug => $perms) {
            $slugs = $perms === ['*'] ? array_keys(self::PERMISSIONS) : $perms;
            foreach ($slugs as $permSlug) {
                $grantRows[] = ['role_id' => $roleIds[$roleSlug], 'permission_id' => $permIds[$permSlug]];
            }
        }
        $this->db->table('role_permissions')->insertBatch($grantRows);
    }
}
