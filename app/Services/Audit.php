<?php

namespace App\Services;

use Config\Database;
use Throwable;

/**
 * Append-only activity log. Every write path that changes money, access or a
 * reservation calls this. Logging must never break the request it describes,
 * so failures are swallowed after being written to the application log.
 */
class Audit
{
    public function log(string $action, ?string $entityType = null, ?int $entityId = null, ?string $summary = null, ?int $actorId = null, ?array $changes = null): void
    {
        try {
            $request = service('request');

            Database::connect()->table('audit_logs')->insert([
                'user_id'     => $actorId ?? service('auth')->id(),
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'summary'     => $summary === null ? null : mb_substr($summary, 0, 255),
                'changes'     => $changes === null ? null : json_encode($changes, JSON_UNESCAPED_UNICODE),
                'ip_address'  => method_exists($request, 'getIPAddress') ? $request->getIPAddress() : null,
                'user_agent'  => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Audit write failed for {action}: {msg}', ['action' => $action, 'msg' => $e->getMessage()]);
        }
    }
}
