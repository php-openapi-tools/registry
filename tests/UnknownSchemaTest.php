<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Registry;

use cebe\openapi\spec\Schema as openAPISchema;
use OpenAPITools\Registry\UnknownSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

final class UnknownSchemaTest extends TestCase
{
    /** @return iterable<string, array{string, string, array<string, mixed>}> */
    public static function unknownSchemaPropertiesProvider(): iterable
    {
        yield 'string schema' => [
            'fallback',
            'Fallback',
            ['type' => 'string'],
        ];

        yield 'integer schema' => [
            'identifier',
            'Identifier',
            ['type' => 'integer', 'title' => 'id'],
        ];
    }

    /** @param array<string, mixed> $schemaData */
    #[Test]
    #[DataProvider('unknownSchemaPropertiesProvider')]
    public function properties(string $name, string $className, array $schemaData): void
    {
        $schema = new openAPISchema($schemaData);

        $unknownSchema = new UnknownSchema($name, $className, $schema);

        self::assertSame($name, $unknownSchema->name);
        self::assertSame($className, $unknownSchema->className);
        self::assertSame($schema, $unknownSchema->schema);
    }
}
