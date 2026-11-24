<?php

declare(strict_types=1);

namespace Fang\Reports;

final class ReportValidator
{
    private const array SINGLE_LINE_LIMITS = [
        'first_name' => 100,
        'last_name' => 100,
        'occurred_when' => 100,
        'duration' => 100,
        'alien_count' => 50,
    ];
    private const array MULTI_LINE_LIMITS = [
        'alien_description' => 1000,
        'alien_actions' => 2000,
        'comments' => 2000,
    ];
    private const array REQUIRED = ['first_name', 'last_name', 'email', 'occurred_when'];
    private const int EMAIL_MAX_LENGTH = 254;

    public function validate(array $input): array
    {
        $values = [];
        $errors = [];

        foreach (self::SINGLE_LINE_LIMITS as $field => $limit) {
            $this->readText($input, $field, $limit, false, $values, $errors);
        }
        foreach (self::MULTI_LINE_LIMITS as $field => $limit) {
            $this->readText($input, $field, $limit, true, $values, $errors);
        }
        $this->readText($input, 'email', self::EMAIL_MAX_LENGTH, false, $values, $errors);
        if (!isset($errors['email']) && $values['email'] !== ''
            && filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }
        foreach (self::REQUIRED as $field) {
            if (!isset($errors[$field]) && $values[$field] === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        $fangSpotted = $input['fang_spotted'] ?? null;
        if ($fangSpotted === 'yes' || $fangSpotted === 'no') {
            $values['fang_spotted'] = $fangSpotted;
        } else {
            $values['fang_spotted'] = '';
            $errors['fang_spotted'] = 'Choose yes or no.';
        }

        return ['values' => $values, 'errors' => $errors];
    }

    private function readText(array $input, string $field, int $limit, bool $multiLine, array &$values, array &$errors): void
    {
        $raw = $input[$field] ?? '';
        if (!is_string($raw) || !mb_check_encoding($raw, 'UTF-8')) {
            $values[$field] = '';
            $errors[$field] = 'Enter valid text.';

            return;
        }
        $value = trim(str_replace("\r\n", "\n", $raw));
        $values[$field] = $value;
        $forbidden = $multiLine ? '/[\x00-\x08\x0B\x0C\x0D\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/';
        if (preg_match($forbidden, $value) === 1) {
            $values[$field] = '';
            $errors[$field] = 'Remove control characters.';

            return;
        }
        if (mb_strlen($value, 'UTF-8') > $limit) {
            $errors[$field] = 'Use at most ' . $limit . ' characters.';
        }
    }
}
