<?php

declare(strict_types=1);

namespace Fang\Security;

use InvalidArgumentException;
use OTPHP\TOTP;
use Psr\Clock\ClockInterface;

final readonly class TotpService
{
    private const int PERIOD = 30;
    private const array ALLOWED_STEP_DRIFT = [-1, 0, 1];

    public function __construct(private ClockInterface $clock)
    {
    }

    public function generateSecret(): string
    {
        return TOTP::generate($this->clock)->getSecret();
    }

    public function provisioningUri(string $secret, string $username): string
    {
        if ($username === '') {
            throw new InvalidArgumentException('Username is required.');
        }
        $totp = $this->totp($secret);
        $totp->setLabel($username);
        $totp->setIssuer('Fang');

        return $totp->getProvisioningUri();
    }

    public function matchingStep(#[\SensitiveParameter] string $secret, #[\SensitiveParameter] string $code): ?int
    {
        if (preg_match('/\A\d{6}\z/', $code) !== 1) {
            return null;
        }
        $totp = $this->totp($secret);
        $currentStep = intdiv($this->clock->now()->getTimestamp(), self::PERIOD);
        $matchedStep = null;
        foreach (self::ALLOWED_STEP_DRIFT as $drift) {
            $step = $currentStep + $drift;
            if ($step >= 0 && hash_equals($totp->at($step * self::PERIOD), $code)) {
                $matchedStep = $step;
            }
        }

        return $matchedStep;
    }

    private function totp(#[\SensitiveParameter] string $secret): TOTP
    {
        if ($secret === '') {
            throw new InvalidArgumentException('TOTP secret is required.');
        }

        return TOTP::createFromSecret($secret, $this->clock);
    }
}
