<?php

declare(strict_types=1);

namespace Fang\Http\Controller;

use Fang\Admin\AdminRepository;
use Fang\Admin\AdminUser;
use Fang\Admin\Authenticator;
use Fang\Http\Renderer;
use Fang\Http\Request;
use Fang\Http\Response;
use Fang\Reports\ReportRepository;
use Fang\Security\CsrfGuard;
use Fang\Security\RateLimiter;
use Fang\Session\SessionStore;
use Psr\Clock\ClockInterface;

final readonly class AdminController
{
    private const string PENDING_ID = 'pending_admin_id';
    private const string PENDING_AT = 'pending_admin_at';
    private const string ADMIN_ID = 'admin_id';
    private const string AUTHENTICATED_AT = 'admin_authenticated_at';
    private const string LAST_SEEN_AT = 'admin_last_seen_at';
    private const int PENDING_TTL = 300;
    private const int IDLE_TTL = 900;
    private const int ABSOLUTE_TTL = 28800;
    private const int LOGIN_LIMIT = 10;
    private const int LOGIN_WINDOW = 900;
    private const int TOTP_LIMIT = 5;
    private const int TOTP_WINDOW = 300;

    public function __construct(
        private Renderer $renderer,
        private SessionStore $session,
        private CsrfGuard $csrf,
        private Authenticator $authenticator,
        private AdminRepository $admins,
        private ReportRepository $reports,
        private RateLimiter $rateLimiter,
        private ClockInterface $clock,
    ) {
    }

    public function showLogin(): Response
    {
        if ($this->currentAdmin() !== null) {
            return Response::redirect('/admin');
        }

        return $this->loginPage();
    }

    public function login(Request $request): Response
    {
        $username = trim($request->postString('username'));
        $allowed = $this->rateLimiter->allow('login-ip', $request->rateLimitSubject(), self::LOGIN_LIMIT, self::LOGIN_WINDOW)
            && $this->rateLimiter->allow('login-user', mb_strtolower(mb_substr($username, 0, 64)), self::LOGIN_LIMIT, self::LOGIN_WINDOW);
        if (!$allowed) {
            return $this->tooManyAttempts(self::LOGIN_WINDOW);
        }
        $admin = $this->authenticator->verifyPassword($username, $request->postString('password'));
        if ($admin === null) {
            return $this->loginPage('Invalid credentials.', 401);
        }
        $this->session->regenerate();
        $this->session->set(self::PENDING_ID, $admin->id);
        $this->session->set(self::PENDING_AT, $this->now());
        $this->csrf->rotate();

        return Response::redirect('/admin/two-factor');
    }

    public function showTwoFactor(): Response
    {
        if ($this->pendingAdmin() === null) {
            return Response::redirect('/admin/login');
        }

        return $this->twoFactorPage();
    }

    public function verifyTwoFactor(Request $request): Response
    {
        $admin = $this->pendingAdmin();
        if ($admin === null) {
            return Response::redirect('/admin/login');
        }
        if (!$this->rateLimiter->allow('totp', (string) $admin->id, self::TOTP_LIMIT, self::TOTP_WINDOW)) {
            return $this->tooManyAttempts(self::TOTP_WINDOW);
        }
        if (!$this->authenticator->verifySecondFactor($admin, trim($request->postString('code')))) {
            return $this->twoFactorPage('Invalid or already used code.', 401);
        }
        $this->session->regenerate();
        $this->session->remove(self::PENDING_ID);
        $this->session->remove(self::PENDING_AT);
        $this->session->set(self::ADMIN_ID, $admin->id);
        $this->session->set(self::AUTHENTICATED_AT, $this->now());
        $this->session->set(self::LAST_SEEN_AT, $this->now());
        $this->csrf->rotate();

        return Response::redirect('/admin');
    }

    public function dashboard(): Response
    {
        if ($this->currentAdmin() === null) {
            return Response::redirect('/admin/login');
        }

        return Response::html($this->renderer->page('admin_dashboard', 'Submitted reports', [
            'csrf' => $this->csrf->token(),
            'reports' => $this->reports->recent(),
        ]));
    }

    public function showReport(string $publicId): Response
    {
        if ($this->currentAdmin() === null) {
            return Response::redirect('/admin/login');
        }
        $report = $this->reports->find($publicId);
        if ($report === null) {
            return Response::html($this->renderer->page('error', 'Not found', ['message' => 'Report not found.']), 404);
        }

        return Response::html($this->renderer->page('admin_report', 'Report details', [
            'csrf' => $this->csrf->token(),
            'report' => $report,
        ]));
    }

    public function deleteReport(string $publicId): Response
    {
        if ($this->currentAdmin() === null) {
            return Response::redirect('/admin/login');
        }
        $this->reports->delete($publicId);

        return Response::redirect('/admin');
    }

    public function logout(): Response
    {
        $this->session->destroy();

        return Response::redirect('/admin/login');
    }

    private function currentAdmin(): ?AdminUser
    {
        $adminId = $this->session->get(self::ADMIN_ID);
        $authenticatedAt = $this->session->get(self::AUTHENTICATED_AT);
        $lastSeenAt = $this->session->get(self::LAST_SEEN_AT);
        if (!is_int($adminId) || !is_int($authenticatedAt) || !is_int($lastSeenAt)) {
            return null;
        }
        $now = $this->now();
        if ($now - $authenticatedAt > self::ABSOLUTE_TTL || $now - $lastSeenAt > self::IDLE_TTL) {
            $this->session->destroy();

            return null;
        }
        $admin = $this->admins->findById($adminId);
        if ($admin === null) {
            $this->session->destroy();

            return null;
        }
        $this->session->set(self::LAST_SEEN_AT, $now);

        return $admin;
    }

    private function pendingAdmin(): ?AdminUser
    {
        $adminId = $this->session->get(self::PENDING_ID);
        $pendingAt = $this->session->get(self::PENDING_AT);
        if (!is_int($adminId) || !is_int($pendingAt) || $this->now() - $pendingAt > self::PENDING_TTL) {
            $this->session->remove(self::PENDING_ID);
            $this->session->remove(self::PENDING_AT);

            return null;
        }

        return $this->admins->findById($adminId);
    }

    private function loginPage(string $error = '', int $status = 200): Response
    {
        return Response::html($this->renderer->page('admin_login', 'Admin sign in', [
            'csrf' => $this->csrf->token(),
            'error' => $error,
        ]), $status);
    }

    private function twoFactorPage(string $error = '', int $status = 200): Response
    {
        return Response::html($this->renderer->page('admin_two_factor', 'Two-factor verification', [
            'csrf' => $this->csrf->token(),
            'error' => $error,
        ]), $status);
    }

    private function tooManyAttempts(int $retryAfter): Response
    {
        return Response::html(
            $this->renderer->page('error', 'Too many requests', ['message' => 'Too many attempts. Try again later.']),
            429,
        )->withHeaders(['Retry-After' => (string) $retryAfter]);
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
