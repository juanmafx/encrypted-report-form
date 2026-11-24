<?php

declare(strict_types=1);

namespace Fang\Tests\Support;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

final class CapturingMailer implements MailerInterface
{
    public array $messages = [];
    public bool $fail = false;

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if ($this->fail) {
            throw new TransportException('Transport unavailable.');
        }
        $this->messages[] = $message;
    }
}
