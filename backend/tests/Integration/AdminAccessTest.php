<?php

declare(strict_types=1);

namespace Fang\Tests\Integration;

use Fang\Tests\Support\AppHarness;
use PHPUnit\Framework\TestCase;

final class AdminAccessTest extends TestCase
{
    private AppHarness $app;

    protected function setUp(): void
    {
        $this->app = new AppHarness();
    }

    private function submitPassword(string $password = AppHarness::PASSWORD, string $username = AppHarness::USERNAME): int
    {
        return $this->app->postWithToken('/admin/login', ['username' => $username, 'password' => $password])->status;
    }

    private function storedReportId(): string
    {
        return (string) $this->app->pdo->query('SELECT public_id FROM abduction_reports')->fetchColumn();
    }

    public function testAnonymousVisitorIsRedirected(): void
    {
        $response = $this->app->get('/admin');

        self::assertSame(303, $response->status);
        self::assertSame('/admin/login', $response->headers['Location']);
    }

    public function testAnonymousVisitorCannotReadAReport(): void
    {
        $this->app->postWithToken('/reports', AppHarness::validReport());
        $response = $this->app->get('/admin/reports/' . $this->storedReportId());

        self::assertSame(303, $response->status);
        self::assertStringNotContainsString('Lovelace', $response->body);
    }

    public function testPasswordAloneDoesNotGrantAccess(): void
    {
        self::assertSame(303, $this->submitPassword());

        $response = $this->app->get('/admin');

        self::assertSame(303, $response->status);
        self::assertSame('/admin/login', $response->headers['Location']);
    }

    public function testWrongPasswordIsRejectedGenerically(): void
    {
        $wrongPassword = $this->app->postWithToken('/admin/login', ['username' => AppHarness::USERNAME, 'password' => 'wrong password value']);
        $unknownUser = $this->app->postWithToken('/admin/login', ['username' => 'nobody', 'password' => 'wrong password value']);

        self::assertSame(401, $wrongPassword->status);
        self::assertSame(401, $unknownUser->status);
        self::assertSame($this->messageOf($wrongPassword->body), $this->messageOf($unknownUser->body));
    }

    private function messageOf(string $body): string
    {
        preg_match('#role="alert">([^<]+)<#', $body, $matches);

        return $matches[1] ?? '';
    }

    public function testFullSignInGrantsAccessAndRegeneratesSession(): void
    {
        $this->app->signIn();

        self::assertSame(200, $this->app->get('/admin')->status);
        self::assertSame(2, $this->app->session->regenerations);
    }

    public function testWrongTotpCodeIsRejected(): void
    {
        $this->submitPassword();
        $code = $this->app->currentCode() === '000000' ? '000001' : '000000';

        self::assertSame(401, $this->app->postWithToken('/admin/two-factor', ['code' => $code])->status);
        self::assertSame(303, $this->app->get('/admin')->status);
    }

    public function testTotpCodeCannotBeReplayed(): void
    {
        $code = $this->app->currentCode();
        $this->app->signIn();
        $this->app->postWithToken('/admin/logout', []);

        $this->submitPassword();

        self::assertSame(401, $this->app->postWithToken('/admin/two-factor', ['code' => $code])->status);
    }

    public function testTotpAttemptsAreRateLimited(): void
    {
        $this->submitPassword();
        $code = $this->app->currentCode() === '000000' ? '000001' : '000000';
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $this->app->postWithToken('/admin/two-factor', ['code' => $code]);
        }

        self::assertSame(429, $this->app->postWithToken('/admin/two-factor', ['code' => $this->app->currentCode()])->status);
    }

    public function testLoginAttemptsAreRateLimited(): void
    {
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            self::assertSame(401, $this->submitPassword('wrong password value'));
        }

        self::assertSame(429, $this->submitPassword());
    }

    public function testPendingTwoFactorExpires(): void
    {
        $this->submitPassword();
        $this->app->clock->advance(301);

        $response = $this->app->postWithToken('/admin/two-factor', ['code' => $this->app->currentCode()]);

        self::assertSame(303, $response->status);
        self::assertSame('/admin/login', $response->headers['Location']);
    }

    public function testIdleSessionExpires(): void
    {
        $this->app->signIn();
        $this->app->clock->advance(901);

        self::assertSame('/admin/login', $this->app->get('/admin')->headers['Location'] ?? null);
    }

    public function testAbsoluteSessionLifetimeIsEnforced(): void
    {
        $this->app->signIn();
        for ($elapsed = 0; $elapsed <= 28800; $elapsed += 600) {
            $this->app->clock->advance(600);
            $this->app->get('/admin');
        }

        self::assertSame('/admin/login', $this->app->get('/admin')->headers['Location'] ?? null);
    }

    public function testAdminReadsDecryptedAndEscapedReport(): void
    {
        $this->app->postWithToken('/reports', AppHarness::validReport(['comments' => '<img src=x onerror=alert(1)>']));
        $this->app->signIn();

        $body = $this->app->get('/admin/reports/' . $this->storedReportId())->body;

        self::assertStringContainsString('Lovelace', $body);
        self::assertStringNotContainsString('<img', $body);
        self::assertStringContainsString('&lt;img', $body);
    }

    public function testDeleteRequiresCsrfToken(): void
    {
        $this->app->postWithToken('/reports', AppHarness::validReport());
        $this->app->signIn();
        $id = $this->storedReportId();

        self::assertSame(403, $this->app->post('/admin/reports/' . $id . '/delete', [])->status);
        self::assertSame($id, $this->storedReportId());
    }

    public function testAdminCanDeleteAReport(): void
    {
        $this->app->postWithToken('/reports', AppHarness::validReport());
        $this->app->signIn();
        $id = $this->storedReportId();

        self::assertSame(303, $this->app->postWithToken('/admin/reports/' . $id . '/delete', [])->status);
        self::assertSame(404, $this->app->get('/admin/reports/' . $id)->status);
    }

    public function testAnonymousVisitorCannotDelete(): void
    {
        $this->app->postWithToken('/reports', AppHarness::validReport());
        $id = $this->storedReportId();

        $this->app->postWithToken('/admin/reports/' . $id . '/delete', []);

        self::assertSame($id, $this->storedReportId());
    }

    public function testLogoutEndsTheSession(): void
    {
        $this->app->signIn();
        $this->app->postWithToken('/admin/logout', []);

        self::assertSame(303, $this->app->get('/admin')->status);
    }

    public function testTotpSecretIsEncryptedAtRest(): void
    {
        $stored = (string) $this->app->pdo->query('SELECT totp_secret_ct FROM admin_users')->fetchColumn();

        self::assertStringNotContainsString(AppHarness::TOTP_SECRET, $stored);
        self::assertStringStartsWith('v1.', $stored);
    }
}
