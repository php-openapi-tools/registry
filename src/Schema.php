<?php

declare(strict_types=1);

namespace OpenAPITools\Registry;

use cebe\openapi\spec\Schema as openAPISchema;
use OpenAPITools\Utils\Utils;
use RuntimeException;

use function array_key_exists;
use function count;
use function is_string;
use function json_encode;
use function spl_object_hash;
use function str_increment;
use function strtoupper;

/** @api */
final class Schema
{
    /** @var array<string, string> */
    private array $splHash = [];
    /** @var array<string, string> */
    private array $json = [];

    /** @var array<string, UnknownSchema> */
    private array $unknownSchemas = [];

    /** @var array<string, string> */
    private array $unknownSchemasJson = [];
    /** @var array<string, array<string>> */
    private array $aliasses = [];

    public function __construct(
        private readonly bool $allowDuplicatedSchemas,
        private readonly bool $useAliasesForDuplication,
    ) {
    }

    /** @throws JsonException */
    public function addClassName(string $className, openAPISchema $schema): void
    {
        if ($schema->type === 'array') {
            $schema = $schema->items;
        }

        if (! $schema instanceof openAPISchema) {
            throw new RuntimeException('Schemas has to be instance of: ' . openAPISchema::class);
        }

        $className                                    = Utils::className($className);
        $this->splHash[spl_object_hash($schema)]      = $className;
        $this->json[$this->encodeSchemaData($schema)] = $className;
    }

    /** @throws JsonException */
    public function get(openAPISchema $schema, string $fallbackName): string
    {
        if ($schema->type === 'array') {
            $schema = $schema->items;
        }

        if (! $schema instanceof openAPISchema) {
            throw new RuntimeException('Schemas has to be instance of: ' . openAPISchema::class);
        }

        $hash = spl_object_hash($schema);
        if (array_key_exists($hash, $this->splHash)) {
            return $this->splHash[$hash];
        }

        $json = $this->encodeSchemaData($schema);
        if (! $this->allowDuplicatedSchemas && array_key_exists($json, $this->json)) {
            return $this->json[$json];
        }

        if (! $this->allowDuplicatedSchemas && array_key_exists($json, $this->unknownSchemasJson)) {
            return $this->unknownSchemasJson[$json];
        }

        $className = Utils::fixKeyword($fallbackName);

        if ($this->allowDuplicatedSchemas && $this->useAliasesForDuplication && array_key_exists($json, $this->json)) {
            $this->aliasses['Schema\\' . $this->json[$json]][] = 'Schema\\' . $className;

            return $className;
        }

        if ($this->allowDuplicatedSchemas && $this->useAliasesForDuplication && array_key_exists($json, $this->unknownSchemasJson)) {
            $this->aliasses['Schema\\' . $this->unknownSchemasJson[$json]][] = 'Schema\\' . $className;

            return $className;
        }

        $suffix = 'a';
        while (array_key_exists($className, $this->unknownSchemas)) {
            $suffix    = str_increment($suffix);
            $className = Utils::fixKeyword($fallbackName . strtoupper($suffix));
        }

        $this->splHash[spl_object_hash($schema)] = $className;
        $this->unknownSchemasJson[$json]         = $className;
        $this->unknownSchemas[$className]        = new UnknownSchema($fallbackName, $className, $schema);

        return $className;
    }

    public function hasUnknownSchemas(): bool
    {
        return count($this->unknownSchemas) > 0;
    }

    /** @return iterable<UnknownSchema> */
    public function unknownSchemas(): iterable
    {
        $unknownSchemas       = $this->unknownSchemas;
        $this->unknownSchemas = [];

        yield from $unknownSchemas;
    }

    /** @return iterable<string> */
    public function aliasesForClassName(string $classname): iterable
    {
        if (! array_key_exists($classname, $this->aliasses)) {
            return;
        }

        yield from $this->aliasses[$classname];
    }

    /** @throws JsonException */
    private function encodeSchemaData(openAPISchema $schema): string
    {
        $json = json_encode($schema->getSerializableData());
        if (! is_string($json)) {
            throw JsonException::createFromPhpError();
        }

        return $json;
    }
}
