<?php

declare(strict_types=1);

namespace Fang\Mail;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final readonly class ReportNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private string $from,
        private string $to,
    ) {
    }

    public function notify(string $publicId): bool
    {
        $email = (new Email())
            ->from(new Address($this->from))
            ->to(new Address($this->to))
            ->subject('New abduction report received')
            ->text("A new report was stored.\nReference: " . $publicId . "\nSign in to the admin area to read it.\n");
        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface) {
            error_log('Fang: report notification could not be delivered.');

            return false;
        }

        return true;
    }
}
