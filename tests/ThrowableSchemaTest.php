<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Registry;

use OpenAPITools\Registry\ThrowableSchema;
use Throwable;
use WyriHaximus\TestUtilities\TestCase;

final class ThrowableSchemaTest extends TestCase
{
    /** @test */
    public function has(): void
    {
        $ts = new ThrowableSchema();

        self::assertFalse($ts->has(Throwable::class));

        $ts->add(Throwable::class);

        self::assertTrue($ts->has(Throwable::class));
    }
}
