<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Registry;

use cebe\openapi\spec\Schema as openAPISchema;
use OpenAPITools\Registry\JsonException;
use OpenAPITools\Registry\Schema;
use OpenAPITools\Registry\UnknownSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WyriHaximus\TestUtilities\TestCase;

use function iterator_to_array;

use const NAN;

final class SchemaTest extends TestCase
{
    #[Test]
    public function addClassNameRegistersClassNameBySchemaJson(): void
    {
        $registry = new Schema(allowDuplicatedSchemas: false, useAliasesForDuplication: false);
        $known    = new openAPISchema(['type' => 'string']);
        $unknown  = new openAPISchema(['type' => 'string']);

        $registry->addClassName('KnownClass', $known);

        self::assertSame('KnownClass', $registry->get($unknown, 'Fallback'));
    }

    /** @return iterable<string, array{string, array<string, mixed>, array<string, mixed>, string}> */
    public static function unwrapsArraySchemaItemsProvider(): iterable
    {
        yield 'addClassName' => [
            'addClassName',
            ['type' => 'array', 'items' => ['type' => 'integer']],
            ['type' => 'integer'],
            'ItemsClass',
        ];

        yield 'get' => [
            'get',
            ['type' => 'array', 'items' => ['type' => 'string']],
            ['type' => 'string'],
            'Items',
        ];
    }

    /**
     * @param array<string, mixed> $arraySchemaData
     * @param array<string, mixed> $itemSchemaData
     */
    #[Test]
    #[DataProvider('unwrapsArraySchemaItemsProvider')]
    public function unwrapsArraySchemaItems(
        string $method,
        array $arraySchemaData,
        array $itemSchemaData,
        string $expectedClassName,
    ): void {
        $registry    = new Schema(allowDuplicatedSchemas: false, useAliasesForDuplication: false);
        $arraySchema = new openAPISchema($arraySchemaData);
        $itemSchema  = new openAPISchema($itemSchemaData);

        if ($method === 'addClassName') {
            $registry->addClassName($expectedClassName, $arraySchema);

            self::assertSame($expectedClassName, $registry->get($itemSchema, 'Fallback'));

            return;
        }

        self::assertSame($expectedClassName, $registry->get($arraySchema, $expectedClassName));
    }

    /** @return iterable<string, array{string}> */
    public static function arraySchemaWithoutItemsProvider(): iterable
    {
        yield 'addClassName' => ['addClassName'];

        yield 'get' => ['get'];
    }

    #[Test]
    #[DataProvider('arraySchemaWithoutItemsProvider')]
    public function throwsWhenArraySchemaItemsAreMissing(string $method): void
    {
        $registry = new Schema(allowDuplicatedSchemas: false, useAliasesForDuplication: false);
        $schema   = new openAPISchema(['type' => 'array']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Schemas has to be instance of: ' . openAPISchema::class);

        if ($method === 'addClassName') {
            $registry->addClassName('MissingItems', $schema);

            return;
        }

        $registry->get($schema, 'MissingItems');
    }

    /** @return iterable<string, array{string}> */
    public static function schemaMethodThatEncodesJsonProvider(): iterable
    {
        yield 'addClassName' => ['addClassName'];

        yield 'get' => ['get'];
    }

    #[Test]
    #[DataProvider('schemaMethodThatEncodesJsonProvider')]
    public function throwsJsonExceptionWhenSchemaDataCannotBeEncoded(string $method): void
    {
        $registry = new Schema(allowDuplicatedSchemas: false, useAliasesForDuplication: false);
        $schema   = new openAPISchema([
            'type'    => 'number',
            'default' => NAN,
        ]);

        $this->expectException(JsonException::class);

        if ($method === 'addClassName') {
            $registry->addClassName('Broken', $schema);

            return;
        }

        $registry->get($schema, 'Broken');
    }

    #[Test]
    public function getReturnsCachedClassNameForSameSchemaObject(): void
    {
        $registry = new Schema(allowDuplicatedSchemas: false, useAliasesForDuplication: false);
        $schema   = new openAPISchema(['type' => 'string', 'title' => 'cached']);

        self::assertSame('Cached', $registry->get($schema, 'Cached'));
        self::assertSame('Cached', $registry->get($schema, 'DifferentFallback'));
    }

    /** @return iterable<string, array{bool, array<string, mixed>, string, string}> */
    public static function returnsClassNameForMatchingSchemaJsonProvider(): iterable
    {
        yield 'known schema from addClassName' => [
            true,
            ['type' => 'string', 'format' => 'uuid'],
            'KnownClass',
            'Fallback',
        ];

        yield 'unknown schema from previous get' => [
            false,
            ['type' => 'integer', 'title' => 'shared'],
            'Shared',
            'DifferentFallback',
        ];
    }

    /** @param array<string, mixed> $schemaData */
    #[Test]
    #[DataProvider('returnsClassNameForMatchingSchemaJsonProvider')]
    public function getReturnsClassNameForMatchingSchemaJson(
        bool $registerWithAddClassName,
        array $schemaData,
        string $registeredClassName,
        string $fallbackName,
    ): void {
        $registry = new Schema(allowDuplicatedSchemas: false, useAliasesForDuplication: false);
        $schema   = new openAPISchema($schemaData);
        $matching = new openAPISchema($schemaData);

        if ($registerWithAddClassName) {
            $registry->addClassName($registeredClassName, $schema);
        } else {
            self::assertSame($registeredClassName, $registry->get($schema, $registeredClassName));
        }

        self::assertSame($registeredClassName, $registry->get($matching, $fallbackName));
    }

    /** @return iterable<string, array{bool, string, array<string, mixed>, string, string, list<string>}> */
    public static function createsAliasWhenDuplicateIsAllowedProvider(): iterable
    {
        yield 'known schema from addClassName' => [
            true,
            'ExistingClass',
            ['type' => 'string', 'title' => 'known'],
            'NewFallback',
            'Schema\\ExistingClass',
            ['Schema\\NewFallback'],
        ];

        yield 'unknown schema from get' => [
            false,
            'First',
            ['type' => 'boolean', 'title' => 'shared'],
            'Second',
            'Schema\\First',
            ['Schema\\Second'],
        ];
    }

    /**
     * @param array<string, mixed> $schemaData
     * @param list<string>         $expectedAliases
     */
    #[Test]
    #[DataProvider('createsAliasWhenDuplicateIsAllowedProvider')]
    public function getCreatesAliasWhenDuplicateIsAllowed(
        bool $registerWithAddClassName,
        string $existingClassName,
        array $schemaData,
        string $newFallbackName,
        string $aliasForClassName,
        array $expectedAliases,
    ): void {
        $registry  = new Schema(allowDuplicatedSchemas: true, useAliasesForDuplication: true);
        $schema    = new openAPISchema($schemaData);
        $duplicate = new openAPISchema($schemaData);

        if ($registerWithAddClassName) {
            $registry->addClassName($existingClassName, $schema);
        } else {
            self::assertSame($existingClassName, $registry->get($schema, $existingClassName));
        }

        self::assertSame($newFallbackName, $registry->get($duplicate, $newFallbackName));
        self::assertSame(
            $expectedAliases,
            iterator_to_array($registry->aliasesForClassName($aliasForClassName), false),
        );
    }

    #[Test]
    public function getAllowsDuplicateSchemasWithoutCreatingAliases(): void
    {
        $registry = new Schema(allowDuplicatedSchemas: true, useAliasesForDuplication: false);
        $schema   = new openAPISchema(['type' => 'number', 'title' => 'duplicate']);

        self::assertSame('First', $registry->get($schema, 'First'));
        self::assertSame(
            'Second',
            $registry->get(new openAPISchema(['type' => 'number', 'title' => 'duplicate']), 'Second'),
        );
    }

    /** @return iterable<string, array{array<string, mixed>, array<string, mixed>, string, string}> */
    public static function incrementsClassNameWhenFallbackNameIsAlreadyUsedProvider(): iterable
    {
        yield 'object schemas' => [
            ['type' => 'object', 'title' => 'first'],
            ['type' => 'object', 'title' => 'second'],
            'Foo',
            'FooB',
        ];

        yield 'string schemas' => [
            ['type' => 'string', 'title' => 'first'],
            ['type' => 'string', 'title' => 'second'],
            'Bar',
            'BarB',
        ];
    }

    /**
     * @param array<string, mixed> $firstSchemaData
     * @param array<string, mixed> $secondSchemaData
     */
    #[Test]
    #[DataProvider('incrementsClassNameWhenFallbackNameIsAlreadyUsedProvider')]
    public function getIncrementsClassNameWhenFallbackNameIsAlreadyUsed(
        array $firstSchemaData,
        array $secondSchemaData,
        string $fallbackName,
        string $expectedSecondClassName,
    ): void {
        $registry = new Schema(allowDuplicatedSchemas: true, useAliasesForDuplication: false);

        self::assertSame($fallbackName, $registry->get(new openAPISchema($firstSchemaData), $fallbackName));
        self::assertSame($expectedSecondClassName, $registry->get(new openAPISchema($secondSchemaData), $fallbackName));
    }

    #[Test]
    public function hasUnknownSchemasIsFalseWhenEmpty(): void
    {
        self::assertFalse(new Schema(allowDuplicatedSchemas: false, useAliasesForDuplication: false)->hasUnknownSchemas());
    }

    #[Test]
    public function unknownSchemasYieldsRegisteredSchemasAndClearsThem(): void
    {
        $registry = new Schema(allowDuplicatedSchemas: false, useAliasesForDuplication: false);
        $schema   = new openAPISchema(['type' => 'string', 'title' => 'unknown']);

        $registry->get($schema, 'Unknown');

        $unknownSchemas = iterator_to_array($registry->unknownSchemas(), false);

        self::assertCount(1, $unknownSchemas);
        self::assertContainsOnlyInstancesOf(UnknownSchema::class, $unknownSchemas);
        self::assertSame('Unknown', $unknownSchemas[0]->className);
        self::assertSame($schema, $unknownSchemas[0]->schema);
        self::assertFalse($registry->hasUnknownSchemas());
    }

    #[Test]
    public function aliasesForClassNameReturnsNothingWhenClassNameIsUnknown(): void
    {
        $registry = new Schema(allowDuplicatedSchemas: true, useAliasesForDuplication: true);

        self::assertSame([], iterator_to_array($registry->aliasesForClassName('Schema\\Missing'), false));
    }
}
