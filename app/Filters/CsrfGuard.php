<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Security\Exceptions\SecurityException;

/**
 * CSRF, with a way back.
 *
 * The framework filter throws a bare 403 when a token does not match, which is
 * correct and unhelpful: the overwhelmingly common cause is not an attack, it
 * is a form that sat open too long, a second tab, or a double-tap on Book. The
 * person then loses a long form to an error page they cannot act on.
 *
 * This keeps the verification exactly as strict — the request is still refused
 * and nothing is written — but sends the browser back to the form it came from
 * with the typed input flashed and a plain-language explanation. Machines still
 * get a hard 403; only humans get the second chance.
 */
class CsrfGuard implements FilterInterface
{
    private const MESSAGE = 'We could not confirm that request came from you — the page had been open a '
        . 'while, or it was submitted twice. Nothing was saved. Your details are still here, so please '
        . 'check them and submit again.';

    /** Never flashed back: old input is stored in the session file on disk. */
    private const SECRET_FIELDS = ['password', 'confirm', 'password_confirm', 'current_password'];

    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return null;
        }

        try {
            service('security')->verify($request);

            return null;
        } catch (SecurityException) {
            // Fall through. Nothing has been written: verify() throws before
            // the request ever reaches a controller.
        }

        // 'error', not 'warning': logger.threshold is 4, and a refused request
        // that leaves no trace at all would be a worse deal than the 403 was.
        log_message('error', 'CSRF token rejected on {method} {uri} — sent back to the form.', [
            'method' => $request->getMethod(),
            'uri'    => (string) $request->getUri(),
        ]);

        if ($this->wantsJson($request)) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON(['error' => 'CSRF token mismatch.']);
        }

        // The spent token is of no use to the form it is going back to, and
        // csrf_field() always emits a fresh one. Keep it out of old input.
        $superglobals = service('superglobals');
        $superglobals->unsetPost(config('Security')->tokenName);
        $request->setGlobal('post', $superglobals->getPostArray());

        // Old input is written to the session file on disk. A sign-in that
        // trips this must not leave the password sitting there.
        foreach (self::SECRET_FIELDS as $field) {
            $superglobals->unsetPost($field);
        }

        return redirect()->to($this->returnUrl($request))
            ->withInput()
            ->with('error', self::MESSAGE);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    /**
     * Where the form was. `previous_url()` prefers the session's own record of
     * the last page, which is exactly right — but it falls back to the Referer
     * header, which the caller controls, so anything off this host is dropped
     * rather than followed.
     */
    private function returnUrl(IncomingRequest $request): string
    {
        $candidate = (string) previous_url();
        $host      = parse_url($candidate, PHP_URL_HOST);

        if ($candidate !== '' && ($host === null || $host === $request->getUri()->getHost())) {
            return $candidate;
        }

        return site_url('/');
    }

    /** Does the caller want data back rather than a page? */
    private function wantsJson(IncomingRequest $request): bool
    {
        return $request->isAJAX()
            || str_contains($request->getHeaderLine('Accept'), 'application/json')
            || str_contains($request->getHeaderLine('Content-Type'), 'json');
    }
}
