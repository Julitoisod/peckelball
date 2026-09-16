<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Read-only endpoints for the live board poller and the booking form.
 * GET only, no session mutation, nothing here writes.
 */
class Api extends BaseController
{
    public function availability(): ResponseInterface|string
    {
        $date = (string) ($this->request->getGet('date') ?? business_date());
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Bad date.']);
        }

        $board = service('availability')->board($date);

        // The poller swaps in fresh markup rather than re-implementing the
        // board's rendering rules in JavaScript.
        if ($this->request->getGet('html') !== null) {
            return view('partials/board', ['board' => $board, 'bookable' => true]);
        }

        return $this->response->setJSON([
            'date'   => $board['date'],
            'totals' => $board['totals'],
            'courts' => array_map(static fn (array $c): array => [
                'id'         => (int) $c['id'],
                'code'       => $c['code'],
                'name'       => $c['name'],
                'now_state'  => $c['now_state'],
                'day_state'  => $c['day_state'],
                'open_slots' => (int) $c['open_slots'],
                'next_open'  => $c['next_open'],
            ], $board['courts']),
        ]);
    }

    public function slots(): ResponseInterface
    {
        $date     = (string) ($this->request->getGet('date') ?? business_date());
        $courtId  = (int) $this->request->getGet('court');
        $duration = (int) ($this->request->getGet('duration') ?? 60);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $courtId < 1) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Bad request.']);
        }

        return $this->response->setJSON([
            'court'    => $courtId,
            'date'     => $date,
            'duration' => $duration,
            'slots'    => service('availability')->startTimesFor($date, $courtId, $duration),
        ]);
    }
}
