<?php

namespace App\Controllers;

/**
 * Open play for players: what is on, and a card to sign up with.
 *
 * Sign-ups go through the same service the desk uses, so the capacity guard
 * and the per-head fee are one rule rather than two.
 */
class OpenPlayPage extends BaseController
{
    public function index(): string
    {
        $openPlay = service('openPlay');
        $sessions = $openPlay->listing(business_date(), true);

        return $this->page('pages/open_play', [
            'title'    => 'Open play | Go Paddle',
            'meta'     => 'Open play at Go Paddle in Trento: turn up, pay per head and play with whoever is on. See what is on and sign up.',
            'sessions' => $sessions,
        ]);
    }

    public function join(int $id)
    {
        $session = service('openPlay')->find($id);
        if ($session === null) {
            return redirect()->to('open-play')->with('error', 'That session is gone.');
        }

        $user  = $this->auth->user();
        $rules = [];

        if ($user === null) {
            $rules['name']  = 'required|min_length[2]|max_length[120]';
            $rules['phone'] = 'required|min_length[7]|max_length[30]';
        }

        if ($rules !== [] && ! $this->validate($rules)) {
            return redirect()->to('open-play')->with('error', implode(' ', $this->validator->getErrors()));
        }

        $result = service('openPlay')->register($id, [
            'name'           => $user !== null ? $this->auth->name() : (string) $this->request->getPost('name'),
            'phone'          => $user !== null ? (string) ($user['phone'] ?? '') : (string) $this->request->getPost('phone'),
            'user_id'        => $this->auth->id(),
            'payment_method' => 'gcash',
            // Unpaid until the desk has seen the receipt and marks them paid.
            'paid'           => false,
        ]);

        if (! $result['ok']) {
            return redirect()->to('open-play')->with('error', $result['error']);
        }

        return redirect()->to('open-play')->with('success', sprintf(
            'You are on the sheet for %s, %s. %s a head — show your GCash receipt at the desk when you arrive.',
            day_label($session['session_date']),
            clock($session['start_time']),
            peso($session['fee'])
        ));
    }
}
