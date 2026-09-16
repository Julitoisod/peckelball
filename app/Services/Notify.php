<?php

namespace App\Services;

use Config\Database;
use Throwable;

/**
 * In-app notifications. A null user id broadcasts to everybody.
 */
class Notify
{
    public function push(?int $userId, string $type, string $title, ?string $body = null, ?string $link = null): void
    {
        try {
            Database::connect()->table('notifications')->insert([
                'user_id'    => $userId,
                'type'       => $type,
                'title'      => mb_substr($title, 0, 160),
                'body'       => $body === null ? null : mb_substr($body, 0, 500),
                'link'       => $link,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Notification write failed: {msg}', ['msg' => $e->getMessage()]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function forUser(int $userId, int $limit = 10): array
    {
        return Database::connect()->table('notifications')
            ->groupStart()->where('user_id', $userId)->orWhere('user_id', null)->groupEnd()
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();
    }

    public function unreadCount(int $userId): int
    {
        return Database::connect()->table('notifications')
            ->groupStart()->where('user_id', $userId)->orWhere('user_id', null)->groupEnd()
            ->where('read_at', null)
            ->countAllResults();
    }

    public function markAllRead(int $userId): void
    {
        Database::connect()->table('notifications')
            ->where('user_id', $userId)
            ->where('read_at', null)
            ->update(['read_at' => date('Y-m-d H:i:s')]);
    }
}
