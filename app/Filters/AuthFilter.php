<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Requires a signed-in user. Arguments name permissions; any one of them
 * grants access. `auth:staff` requires a staff role rather than a permission.
 *
 *   $routes->get('admin', ..., ['filter' => 'auth:dashboard.view']);
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = service('auth');

        if (! $auth->check()) {
            if ($request->isAJAX()) {
                return service('response')->setStatusCode(401)->setJSON(['error' => 'Sign in required.']);
            }

            return redirect()->to('/login?next=' . rawurlencode((string) current_url(true)->getPath()))
                ->with('error', 'Sign in to continue.');
        }

        if (empty($arguments)) {
            return null;
        }

        $needsStaff = in_array('staff', $arguments, true);
        $perms      = array_values(array_diff($arguments, ['staff']));

        if ($needsStaff && ! $auth->isStaff()) {
            return $this->deny($request);
        }

        if ($perms !== [] && ! $auth->can(...$perms)) {
            return $this->deny($request);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function deny(RequestInterface $request)
    {
        service('audit')->log('access.denied', 'route', null, (string) current_url(true)->getPath());

        if ($request->isAJAX()) {
            return service('response')->setStatusCode(403)->setJSON(['error' => 'Not permitted.']);
        }

        // Somewhere this account can actually open. Sending a signed-in player
        // to /admin denied them there too, and the browser looped until it gave
        // up with ERR_TOO_MANY_REDIRECTS; the same happened to a staff member
        // without dashboard.view. Never bounce to the page we are already on.
        $target = service('auth')->isStaff() ? 'admin' : 'account';
        $path   = trim((string) current_url(true)->getPath(), '/');

        if ($path === $target || str_ends_with($path, '/' . $target)) {
            $target = '/';
        }

        return redirect()->to($target)->with('error', 'You do not have access to that.');
    }
}
