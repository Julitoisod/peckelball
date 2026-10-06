<?php

namespace App\Controllers;

use App\Services\Auth;
use CodeIgniter\Controller;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    protected Auth $auth;
    protected BaseConnection $db;

    /** @var list<string> */
    protected $helpers = ['url', 'form', 'bernie'];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->auth = service('auth');
        $this->db   = Database::connect();
    }

    /**
     * Render a public page with the shell data every layout needs.
     *
     * @param array<string, mixed> $data
     */
    protected function page(string $view, array $data = [], string $layout = 'layouts/public'): string
    {
        $data['auth']    = $this->auth;
        $data['nav']     = $data['nav'] ?? '';
        $data['title']   = $data['title'] ?? 'Go Paddle';
        $data['meta']    = $data['meta'] ?? 'Five pickleball courts in Trento, Agusan del Sur. Live availability, online booking, coaching and tournaments.';
        $data['layout']  = $layout;
        $data['bodyEnd'] = $data['bodyEnd'] ?? '';

        return view($view, $data);
    }
}
