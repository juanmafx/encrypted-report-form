<?php

declare(strict_types=1);

namespace Fang\Tests\Unit;

use Fang\Reports\ReportValidator;
use Fang\Tests\Support\AppHarness;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReportValidatorTest extends TestCase
{
    public function testAcceptsAValidReport(): void
    {
        $result = (new ReportValidator())->validate(AppHarness::validReport(['first_name' => '  Ada  ']));

        self::assertSame([], $result['errors']);
        self::assertSame('Ada', $result['values']['first_name']);
    }

    #[DataProvider('invalidFields')]
    public function testRejectsInvalidField(string $field, mixed $value): void
    {
        $result = (new ReportValidator())->validate(AppHarness::validReport([$field => $value]));

        self::assertArrayHasKey($field, $result['errors']);
    }

    public static function invalidFields(): array
    {
        return [
            'email header injection' => ['email', "a@example.test\r\nBcc: victim@example.test"],
            'email newline injection' => ['email', "a@example.test\nBcc: victim@example.test"],
            'malformed email' => ['email', 'not-an-email'],
            'missing email' => ['email', ''],
            'oversized email' => ['email', str_repeat('a', 250) . '@example.test'],
            'array parameter' => ['first_name', ['nested']],
            'newline in single-line field' => ['last_name', "Love\nlace"],
            'null byte' => ['comments', "before\0after"],
            'invalid utf-8' => ['comments', "\xC3\x28"],
            'oversized comment' => ['comments', str_repeat('x', 2001)],
            'missing first name' => ['first_name', ''],
            'unexpected fang value' => ['fang_spotted', 'maybe'],
            'array fang value' => ['fang_spotted', ['yes']],
        ];
    }

    public function testKeepsNewlinesInMultiLineFields(): void
    {
        $result = (new ReportValidator())->validate(AppHarness::validReport(['comments' => "one\r\ntwo"]));

        self::assertSame([], $result['errors']);
        self::assertSame("one\ntwo", $result['values']['comments']);
    }

    public function testCountsCharactersNotBytes(): void
    {
        $result = (new ReportValidator())->validate(AppHarness::validReport(['first_name' => str_repeat('é', 100)]));

        self::assertArrayNotHasKey('first_name', $result['errors']);
    }
}
