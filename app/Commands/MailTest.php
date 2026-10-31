<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Sends one message through the settings in .env, the same way a booking
 * approval does, to prove the mailbox works without touching a booking.
 *
 * Run: php spark mail:test someone@example.com
 */
class MailTest extends BaseCommand
{
    protected $group       = 'GoPaddle';
    protected $name        = 'mail:test';
    protected $description = 'Send a test email using the settings in .env.';
    protected $usage       = 'mail:test <address>';

    public function run(array $params)
    {
        $to = (string) ($params[0] ?? '');
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            CLI::error('Give an email address: php spark mail:test someone@example.com');

            return EXIT_ERROR;
        }

        $sent = service('bernieNotify')->email($to, 'Go Paddle test email', "This is a test from the Go Paddle booking site.\nApproved and rejected bookings will be sent from this address.");

        if (! $sent) {
            CLI::error('Not sent. The reason is in writable/logs.');

            return EXIT_ERROR;
        }

        CLI::write('Sent.', 'green');

        return EXIT_SUCCESS;
    }
}
