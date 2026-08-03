<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Registry;

use OpenAPITools\Registry\JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

use function json_decode;
use function str_repeat;

use const JSON_ERROR_DEPTH;
use const JSON_ERROR_SYNTAX;

final class JsonExceptionTest extends TestCase
{
    /** @return iterable<string, array{string, int}> */
    public static function phpJsonErrorProvider(): iterable
    {
        yield 'syntax error' => [
            'triggerSyntaxError',
            JSON_ERROR_SYNTAX,
        ];

        yield 'depth error' => [
            'triggerDepthError',
            JSON_ERROR_DEPTH,
        ];
    }

    #[Test]
    #[DataProvider('phpJsonErrorProvider')]
    public function createFromPhpError(string $triggerMethod, int $expectedCode): void
    {
        match ($triggerMethod) {
            'triggerSyntaxError' => $this->triggerSyntaxError(),
            'triggerDepthError' => $this->triggerDepthError(),
            default => self::fail('Unknown trigger method: ' . $triggerMethod),
        };

        $exception = JsonException::createFromPhpError();

        self::assertSame($expectedCode, $exception->getCode());
        self::assertNotSame('', $exception->getMessage());
    }

    private function triggerSyntaxError(): void
    {
        json_decode('{');
    }

    private function triggerDepthError(): void
    {
        json_decode(str_repeat('[', 5120));
    }
}
