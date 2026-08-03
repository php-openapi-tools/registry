<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Registry;

use cebe\openapi\spec\Schema as openAPISchema;
use OpenAPITools\Registry\Contract;
use OpenAPITools\Registry\UnknownSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WyriHaximus\TestUtilities\TestCase;

use function iterator_to_array;

final class ContractTest extends TestCase
{
    #[Test]
    public function getRegistersAndReturnsClassName(): void
    {
        $contract = new Contract();
        $schema   = new openAPISchema(['type' => 'string']);

        self::assertSame('Foo', $contract->get($schema, 'Foo'));
        self::assertTrue($contract->hasContracts());
    }

    #[Test]
    public function getReturnsCachedClassNameForSameSchemaObject(): void
    {
        $contract = new Contract();
        $schema   = new openAPISchema(['type' => 'string']);

        self::assertSame('Foo', $contract->get($schema, 'Foo'));
        self::assertSame('Foo', $contract->get($schema, 'Bar'));
    }

    /** @return iterable<string, array{array<string, mixed>, array<string, mixed>, string, string}> */
    public static function incrementsClassNameWhenFallbackNameIsAlreadyUsedProvider(): iterable
    {
        yield 'string schemas' => [
            ['type' => 'string', 'title' => 'first'],
            ['type' => 'string', 'title' => 'second'],
            'Foo',
            'FooB',
        ];

        yield 'boolean schemas' => [
            ['type' => 'boolean', 'title' => 'first'],
            ['type' => 'boolean', 'title' => 'second'],
            'Flag',
            'FlagB',
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
        $contract = new Contract();

        self::assertSame($fallbackName, $contract->get(new openAPISchema($firstSchemaData), $fallbackName));
        self::assertSame($expectedSecondClassName, $contract->get(new openAPISchema($secondSchemaData), $fallbackName));
    }

    /** @return iterable<string, array{array<string, mixed>, string, string}> */
    public static function unwrapsArraySchemaItemsProvider(): iterable
    {
        yield 'string items' => [
            ['type' => 'array', 'items' => ['type' => 'string']],
            'Items',
            'Items',
        ];

        yield 'integer items' => [
            ['type' => 'array', 'items' => ['type' => 'integer']],
            'Numbers',
            'Numbers',
        ];
    }

    /** @param array<string, mixed> $schemaData */
    #[Test]
    #[DataProvider('unwrapsArraySchemaItemsProvider')]
    public function getUnwrapsArraySchemaItems(
        array $schemaData,
        string $fallbackName,
        string $expectedClassName,
    ): void {
        $contract = new Contract();

        self::assertSame($expectedClassName, $contract->get(new openAPISchema($schemaData), $fallbackName));
    }

    #[Test]
    public function getThrowsWhenArraySchemaItemsAreMissing(): void
    {
        $contract = new Contract();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Schemas has to be instance of: ' . openAPISchema::class);

        $contract->get(new openAPISchema(['type' => 'array']), 'MissingItems');
    }

    #[Test]
    public function hasContractsIsFalseWhenEmpty(): void
    {
        self::assertFalse(new Contract()->hasContracts());
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function contractsYieldsRegisteredSchemasProvider(): iterable
    {
        yield 'boolean schema' => [
            ['type' => 'boolean'],
            'Flag',
        ];

        yield 'string schema' => [
            ['type' => 'string', 'title' => 'name'],
            'Name',
        ];
    }

    /** @param array<string, mixed> $schemaData */
    #[Test]
    #[DataProvider('contractsYieldsRegisteredSchemasProvider')]
    public function contractsYieldsRegisteredSchemasAndClearsThem(array $schemaData, string $className): void
    {
        $contract = new Contract();
        $schema   = new openAPISchema($schemaData);

        $contract->get($schema, $className);

        $contracts = iterator_to_array($contract->contracts(), false);

        self::assertCount(1, $contracts);
        self::assertContainsOnlyInstancesOf(UnknownSchema::class, $contracts);
        self::assertSame($className, $contracts[0]->className);
        self::assertSame($schema, $contracts[0]->schema);
        self::assertFalse($contract->hasContracts());
    }
}
