<?php

namespace App\Services;

use Config\Database;
use Throwable;

/**
 * In-app notifications, where a null user id broadcasts to everybody, and the
 * email that reaches a guest who has no account to be notified on.
 */
class Notify
{
    /**
     * Sends an email, with $html as the styled version of the plain $body. Never
     * throws: the booking has already moved by now, and a mail server being down
     * must not undo that or break the desk's screen. Sends nothing until
     * `email.fromEmail` is set in .env.
     */
    public function email(?string $to, string $subject, string $body, ?string $html = null): bool
    {
        $config = config('Email');

        if ($to === null || $to === '') {
            return false;
        }
        if ($config->fromEmail === '') {
            log_message('error', 'Email not sent: email.fromEmail is not set in .env.');

            return false;
        }

        try {
            $mail = service('email');
            $mail->setFrom($config->fromEmail, $config->fromName ?: 'Go Paddle');
            $mail->setTo($to);
            $mail->setSubject($subject);

            if ($html === null) {
                $mail->setMessage($body);
            } else {
                $mail->setMailType('html');
                $mail->setMessage($html);
                $mail->setAltMessage($body);
            }

            if ($mail->send(false)) {
                return true;
            }

            log_message('error', 'Email failed: {msg}', ['msg' => $mail->printDebugger([])]);
        } catch (Throwable $e) {
            log_message('error', 'Email failed: {msg}', ['msg' => $e->getMessage()]);
        }

        return false;
    }

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
