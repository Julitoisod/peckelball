<?php

namespace App\Services;

use Config\Database;

/**
 * Typed access to the `settings` table, loaded once per request.
 */
class Settings
{
    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $this->load();

        return $this->cache[$key] ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    /** @return array<string, mixed> */
    public function all(?string $group = null): array
    {
        $this->load();

        if ($group === null) {
            return $this->cache;
        }

        $db   = Database::connect();
        $keys = array_column(
            $db->table('settings')->select('key')->where('group', $group)->get()->getResultArray(),
            'key'
        );

        return array_intersect_key($this->cache, array_flip($keys));
    }

    public function set(string $key, mixed $value): void
    {
        Database::connect()->table('settings')
            ->where('key', $key)
            ->update(['value' => (string) $value, 'updated_at' => date('Y-m-d H:i:s')]);

        $this->cache = null;
    }

    private function load(): void
    {
        if ($this->cache !== null) {
            return;
        }

        $this->cache = [];

        // Settings are read on every page including the 404 handler, so a
        // missing table (fresh checkout, pre-migration) must not be fatal.
        try {
            $rows = Database::connect()->table('settings')->get()->getResultArray();
        } catch (\Throwable) {
            return;
        }

        foreach ($rows as $row) {
            $this->cache[$row['key']] = match ($row['type']) {
                'int'  => (int) $row['value'],
                'bool' => (bool) $row['value'],
                'json' => json_decode((string) $row['value'], true),
                default => $row['value'],
            };
        }
    }
}
