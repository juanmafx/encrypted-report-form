<?php

declare(strict_types=1);

namespace Fang\Http\Controller;

use Fang\Http\Renderer;
use Fang\Http\Request;
use Fang\Http\Response;
use Fang\Mail\ReportNotifier;
use Fang\Reports\ReportRepository;
use Fang\Reports\ReportValidator;
use Fang\Security\CsrfGuard;
use Fang\Security\RateLimiter;

final readonly class ReportController
{
    private const int SUBMISSION_LIMIT = 10;
    private const int SUBMISSION_WINDOW = 3600;

    public function __construct(
        private Renderer $renderer,
        private CsrfGuard $csrf,
        private ReportValidator $validator,
        private ReportRepository $reports,
        private ReportNotifier $notifier,
        private RateLimiter $rateLimiter,
    ) {
    }

    public function showForm(): Response
    {
        return $this->form([], []);
    }

    public function submit(Request $request): Response
    {
        if (!$this->rateLimiter->allow('report', $request->rateLimitSubject(), self::SUBMISSION_LIMIT, self::SUBMISSION_WINDOW)) {
            return Response::html(
                $this->renderer->page('error', 'Too many requests', ['message' => 'Too many submissions. Try again later.']),
                429,
            )->withHeaders(['Retry-After' => (string) self::SUBMISSION_WINDOW]);
        }
        $result = $this->validator->validate($request->post);
        if ($result['errors'] !== []) {
            return $this->form($result['values'], $result['errors'], 422);
        }
        $publicId = $this->reports->insert($result['values']);
        $this->notifier->notify($publicId);

        return Response::redirect('/thanks');
    }

    public function thanks(): Response
    {
        return Response::html($this->renderer->page('thanks', 'Report received'));
    }

    private function form(array $values, array $errors, int $status = 200): Response
    {
        return Response::html($this->renderer->page('report_form', 'Report an abduction', [
            'csrf' => $this->csrf->token(),
            'values' => $values,
            'errors' => $errors,
        ]), $status);
    }
}
