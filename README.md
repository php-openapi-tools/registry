# registry

Class registries for [OpenAPI Tools](https://github.com/php-openapi-tools) code generators. Maps `cebe/php-openapi` schema objects to stable PHP class names while gathering an OpenAPI spec, tracks inline contract shapes, and records schemas used as error responses.

![Continuous Integration](https://github.com/php-openapi-tools/registry/workflows/Continuous%20Integration/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/openapi-tools/registry/v/stable.png)](https://packagist.org/packages/openapi-tools/registry)
[![Total Downloads](https://poser.pugx.org/openapi-tools/registry/downloads.png)](https://packagist.org/packages/openapi-tools/registry/stats)
[![License](https://poser.pugx.org/openapi-tools/registry/license.png)](https://packagist.org/packages/openapi-tools/registry)

## Installation

To install via [Composer](https://getcomposer.org/), use the command below, it will automatically detect the latest version and bind it with `^`.

```
composer require openapi-tools/registry
```

## Components

| Class | Purpose |
| --- | --- |
| `Schema` | Resolves OpenAPI schemas to PHP class names with deduplication, alias support, and discovery of unknown schemas |
| `Contract` | Resolves inline object schemas to contract class names during gathering |
| `UnknownSchema` | Value object for a schema discovered during gathering that was not pre-registered |
| `ThrowableSchema` | Tracks schema class names that represent error responses |
| `JsonException` | Thrown when schema data cannot be JSON-encoded for comparison |

## Usage

These registries are created and passed through the gathering pipeline in [`openapi-tools/gatherer`](https://github.com/php-openapi-tools/gatherer). The examples below show how each registry behaves on its own.

### Schema registry

The schema registry maps OpenAPI schema objects to PHP class names. Pre-register component schemas with `addClassName()` before resolving references so naming stays consistent across the spec.

```php
use cebe\openapi\spec\Schema as OpenApiSchema;
use OpenAPITools\Registry\Schema;

$registry = new Schema(
    allowDuplicatedSchemas: false,
    useAliasesForDuplication: false,
);

$userSchema = new OpenApiSchema(['type' => 'object', 'title' => 'User']);
$registry->addClassName('User', $userSchema);

// Same JSON structure resolves to the pre-registered name.
$matching = new OpenApiSchema(['type' => 'object', 'title' => 'User']);
$registry->get($matching, 'Fallback'); // 'User'

// A new schema gets a class name from the fallback and is queued as unknown.
$other = new OpenApiSchema(['type' => 'string', 'format' => 'uuid']);
$registry->get($other, 'Uuid'); // 'Uuid'
```

Array schemas are unwrapped to their `items` schema before lookup. When the same PHP object is passed again, the cached class name is returned regardless of the fallback name.

#### Unknown schemas

Schemas resolved via `get()` that were not pre-registered are collected and can be drained for a second gathering pass. This is how [`Gatherer`](https://github.com/php-openapi-tools/gatherer) discovers nested schemas referenced during property and operation gathering:

```php
while ($registry->hasUnknownSchemas()) {
    foreach ($registry->unknownSchemas() as $unknown) {
        // $unknown->name, $unknown->className, $unknown->schema
    }
}
```

Each call to `unknownSchemas()` yields the current batch and clears the internal queue.

#### Duplicates and aliases

When `allowDuplicatedSchemas` is `true` and `useAliasesForDuplication` is `true`, a schema with the same JSON structure as an existing one still receives a new class name, and the duplicate is recorded as an alias:

```php
$registry = new Schema(
    allowDuplicatedSchemas: true,
    useAliasesForDuplication: true,
);

$schema = new OpenApiSchema(['type' => 'string', 'title' => 'shared']);
$registry->addClassName('ExistingClass', $schema);

$duplicate = new OpenApiSchema(['type' => 'string', 'title' => 'shared']);
$registry->get($duplicate, 'NewFallback'); // 'NewFallback'

iterator_to_array($registry->aliasesForClassName('Schema\\ExistingClass'));
// ['Schema\\NewFallback']
```

When duplicates are allowed but aliases are disabled, each occurrence gets its own class name with no alias mapping.

When the fallback name is already taken by another unknown schema, the registry appends an incrementing suffix (`Foo`, `FooB`, `FooC`, …).

### Contract registry

The contract registry assigns class names to inline object schemas used as contracts (nested shapes embedded in a parent schema). It does not deduplicate by JSON content—only by object identity and fallback name collision:

```php
use cebe\openapi\spec\Schema as OpenApiSchema;
use OpenAPITools\Registry\Contract;

$contract = new Contract();

$inline = new OpenApiSchema([
    'type'       => 'object',
    'properties' => ['id' => ['type' => 'integer']],
]);

$contract->get($inline, 'Contract\\User\\Profile'); // 'Contract\\User\\Profile'

foreach ($contract->contracts() as $registered) {
    // $registered->name, $registered->className, $registered->schema
}
```

Like `unknownSchemas()` on the schema registry, `contracts()` drains and clears the internal queue.

### Throwable schema registry

Operations that return error status codes register their response schema class names so generators can treat them as throwables:

```php
use OpenAPITools\Registry\ThrowableSchema;

$throwables = new ThrowableSchema();

$throwables->add('Schema\\NotFound');
$throwables->has('Schema\\NotFound'); // true
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

The MIT License (MIT)

Copyright (c) 2026 Cees-Jan Kiewiet

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
