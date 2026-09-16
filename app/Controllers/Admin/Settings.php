<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * The settings table, edited in place.
 *
 * Two rules hold this together. The list of editable keys and each key's type
 * come from the table, never from the form, so a crafted POST cannot invent a
 * setting or change how one is parsed. And a save is all-or-nothing: a single
 * bad time would otherwise leave the facility half-configured, with the board
 * generated from one value and the copy from another.
 */
class Settings extends BaseController
{
    /** Keys whose value is an `H:i` clock time. These drive the board. */
    private const TIME_KEY = '/\.(open|close|peak_start|peak_end|last_start)$/';

    public function index(): string
    {
        return $this->page('admin/settings', [
            'title'  => 'Settings | Bernie Pickleball',
            'groups' => $this->grouped(),
            'posted' => (array) (session()->getFlashdata('settingsPosted') ?? []),
            'errors' => (array) (session()->getFlashdata('settingsErrors') ?? []),
        ], 'layouts/admin');
    }

    public function save()
    {
        $posted  = (array) $this->request->getPost('s');
        $errors  = [];
        $changes = [];

        foreach ($this->rows() as $row) {
            $key = $row['key'];

            // An unchecked checkbox posts nothing at all, so for bool rows a
            // missing key means false. For every other type it means the field
            // was not on the form, which is not the same as "clear it".
            if ($row['type'] !== 'bool' && ! array_key_exists($key, $posted)) {
                continue;
            }

            $raw = $row['type'] === 'bool'
                ? (empty($posted[$key]) ? '0' : '1')
                : trim((string) $posted[$key]);

            [$clean, $error] = $this->clean($key, $row['type'], $raw);

            if ($error !== null) {
                $errors[$key] = $error;
            } elseif ($clean !== (string) $row['value']) {
                $changes[$key] = ['from' => $row['value'], 'to' => $clean];
            }
        }

        if ($errors !== []) {
            return redirect()->to('admin/settings')
                ->with('settingsErrors', $errors)
                ->with('settingsPosted', $posted)
                ->with('error', count($errors) === 1
                    ? 'Nothing saved. One value needs a fix.'
                    : 'Nothing saved. ' . count($errors) . ' values need a fix.');
        }

        if ($changes === []) {
            return redirect()->to('admin/settings')->with('success', 'No changes to save.');
        }

        $settings = service('siteSettings');
        foreach ($changes as $key => $change) {
            $settings->set($key, $change['to']);
        }

        service('audit')->log(
            'settings.updated',
            'settings',
            null,
            implode(', ', array_keys($changes)),
            $this->auth->id(),
            $changes
        );

        return redirect()->to('admin/settings')->with('success', count($changes) === 1
            ? 'Saved. 1 setting updated.'
            : 'Saved. ' . count($changes) . ' settings updated.');
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        return $this->db->table('settings')->orderBy('group')->orderBy('key')->get()->getResultArray();
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function grouped(): array
    {
        $groups = [];
        foreach ($this->rows() as $row) {
            $groups[$row['group']][] = $row;
        }

        return $groups;
    }

    /**
     * @return array{0: string|null, 1: string|null} cleaned value, or an error
     */
    private function clean(string $key, string $type, string $raw): array
    {
        if ($type === 'bool') {
            return [$raw === '1' ? '1' : '0', null];
        }

        if ($type === 'int') {
            return preg_match('/^\d{1,6}$/', $raw) === 1
                ? [(string) (int) $raw, null]
                : [null, 'Whole number only, up to 6 digits.'];
        }

        if ($type === 'json') {
            json_decode($raw, true);

            return json_last_error() === JSON_ERROR_NONE ? [$raw, null] : [null, 'Not valid JSON.'];
        }

        // A malformed opening time silently empties the whole availability
        // board, so times are the one string shape that is enforced.
        if (preg_match(self::TIME_KEY, $key) === 1) {
            return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $raw) === 1
                ? [$raw, null]
                : [null, 'Use a 24-hour time, for example 06:00 or 21:30.'];
        }

        return mb_strlen($raw) <= 500 ? [$raw, null] : [null, 'Keep it under 500 characters.'];
    }
}
