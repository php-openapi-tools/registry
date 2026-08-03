<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Registry;

use OpenAPITools\Registry\ThrowableSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Throwable;
use WyriHaximus\TestUtilities\TestCase;

final class ThrowableSchemaTest extends TestCase
{
    /** @return iterable<string, array{class-string}> */
    public static function throwableClassProvider(): iterable
    {
        yield 'throwable' => [Throwable::class];

        yield 'exception' => [Throwable::class];

        yield 'runtime exception' => [RuntimeException::class];
    }

    #[Test]
    #[DataProvider('throwableClassProvider')]
    public function has(string $class): void
    {
        $throwableSchema = new ThrowableSchema();

        self::assertFalse($throwableSchema->has($class));

        $throwableSchema->add($class);

        self::assertTrue($throwableSchema->has($class));
    }
}
