<?php

namespace App\Services;

use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Kreait\Firebase\Factory;
use RuntimeException;

class FirebaseIdentityService
{
    private ?Auth $auth = null;

    public function enabled(): bool
    {
        return (bool) config('firebase.enabled');
    }

    /** @return array{uid: string, email: string, email_verified: bool} */
    public function verifyIdToken(string $idToken): array
    {
        // Check revoked/disabled sessions too. This adds one trusted server-side
        // Firebase check at login so a revoked token cannot open a Laravel session.
        $token = $this->auth()->verifyIdToken($idToken, true);
        $claims = $token->claims();
        $uid = (string) $claims->get('sub', '');
        $email = mb_strtolower(trim((string) $claims->get('email', '')));

        if ($uid === '' || $email === '') {
            throw new RuntimeException('Firebase token does not identify an email account.');
        }

        return [
            'uid' => $uid,
            'email' => $email,
            'email_verified' => (bool) $claims->get('email_verified', false),
        ];
    }

    /** @param array{name: string, email: string, password: string} $data */
    public function createUser(array $data): string
    {
        return $this->auth()->createUser([
            'email' => $data['email'],
            'password' => $data['password'],
            'displayName' => $data['name'],
            'disabled' => false,
        ])->uid;
    }

    /** @param array{name: string, email: string, password?: string|null} $data */
    public function updateUser(string $uid, array $data): void
    {
        $properties = ['email' => $data['email'], 'displayName' => $data['name']];
        if (! empty($data['password'])) {
            $properties['password'] = $data['password'];
        }
        $this->auth()->updateUser($uid, $properties);
    }

    public function findUidByEmail(string $email): ?string
    {
        try {
            return $this->auth()->getUserByEmail($email)->uid;
        } catch (UserNotFound) {
            return null;
        }
    }

    public function deleteUser(string $uid): void
    {
        $this->auth()->deleteUser($uid);
    }

    private function auth(): Auth
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Firebase Authentication is disabled.');
        }
        if ($this->auth) {
            return $this->auth;
        }

        $projectId = trim((string) config('firebase.project_id'));
        $credentials = trim((string) config('firebase.credentials'));
        if ($projectId === '' || $credentials === '' || ! is_file($credentials) || ! is_readable($credentials)) {
            throw new RuntimeException('Firebase server credentials are not configured.');
        }

        $this->auth = (new Factory)
            ->withServiceAccount($credentials)
            ->withProjectId($projectId)
            ->createAuth();

        return $this->auth;
    }
}
