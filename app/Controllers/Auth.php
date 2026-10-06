<?php

namespace App\Controllers;

class Auth extends BaseController
{
    public function login(): string
    {
        return $this->page('pages/auth_login', [
            'title' => 'Sign in | Go Paddle',
            'meta'  => 'Sign in to book courts, manage your reservations and track your membership at Go Paddle.',
            'next'  => (string) ($this->request->getGet('next') ?? ''),
        ]);
    }

    public function attemptLogin()
    {
        if (! $this->validate([
            'email'    => 'required|valid_email',
            'password' => 'required',
        ])) {
            return redirect()->back()->withInput()->with('error', 'Enter your email and password.');
        }

        $result = $this->auth->attempt(
            (string) $this->request->getPost('email'),
            (string) $this->request->getPost('password')
        );

        if (! $result['ok']) {
            return redirect()->back()->withInput()->with('error', $result['error']);
        }

        $next = (string) $this->request->getPost('next');
        $safe = $next !== '' && str_starts_with($next, '/') && ! str_starts_with($next, '//');

        return redirect()->to($safe ? $next : ($this->auth->isStaff() ? '/admin' : '/account'))
            ->with('success', 'Signed in as ' . $this->auth->name() . '.');
    }

    public function register(): string
    {
        return $this->page('pages/auth_register', [
            'title' => 'Create a player account | Go Paddle',
            'meta'  => 'Create a free player account to book courts faster, keep your booking history and join tournaments.',
        ]);
    }

    public function attemptRegister()
    {
        if (! $this->validate([
            'first_name' => 'required|min_length[2]|max_length[60]',
            'last_name'  => 'required|min_length[2]|max_length[60]',
            'email'      => 'required|valid_email|max_length[160]',
            'phone'      => 'permit_empty|max_length[30]',
            'password'   => 'required|min_length[10]|max_length[200]',
            'confirm'    => 'required|matches[password]',
        ], [
            'password' => ['min_length' => 'Use at least 10 characters. This account can spend money.'],
            'confirm'  => ['matches' => 'The two passwords do not match.'],
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $result = $this->auth->register([
            'first_name'  => (string) $this->request->getPost('first_name'),
            'last_name'   => (string) $this->request->getPost('last_name'),
            'email'       => (string) $this->request->getPost('email'),
            'phone'       => (string) $this->request->getPost('phone'),
            'password'    => (string) $this->request->getPost('password'),
            'skill_level' => (string) $this->request->getPost('skill_level'),
        ]);

        if (! $result['ok']) {
            return redirect()->back()->withInput()->with('error', $result['error']);
        }

        return redirect()->to('/account')->with('success', 'Account created. Book a court whenever you are ready.');
    }

    public function logout()
    {
        $this->auth->logout();

        return redirect()->to('/')->with('success', 'Signed out.');
    }
}
