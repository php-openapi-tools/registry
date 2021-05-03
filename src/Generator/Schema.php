<?php

namespace ApiClients\Tools\OpenApiClientGenerator\Generator;

use cebe\openapi\spec\Schema as OpenAPiSchema;
use PhpParser\Builder\Param;
use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use Psr\Http\Message\RequestInterface;
use RingCentral\Psr7\Request;

final class Schema
{
    /**
     * @param string $name
     * @param string $namespace
     * @param string $className
     * @param OpenAPiSchema $schema
     * @return iterable<Node>
     */
    public static function generate(string $name, string $namespace, string $className, OpenAPiSchema $schema, array $schemaClassNameMap): Node
    {
        $factory = new BuilderFactory();
        $stmt = $factory->namespace($namespace);

        $class = $factory->class($className)->makeFinal()->addStmt(
            new Node\Stmt\ClassConst(
                [
                    new Node\Const_(
                        'SCHEMA_TITLE',
                        new Node\Scalar\String_(
                            $schema->title ?? $name
                        )
                    ),
                ],
                Class_::MODIFIER_PUBLIC
            )
        )->addStmt(
            new Node\Stmt\ClassConst(
                [
                    new Node\Const_(
                        'SPL_HASH',
                        new Node\Scalar\String_(
                            spl_object_hash($schema)
                        )
                    ),
                ],
                Class_::MODIFIER_PUBLIC
        ))->addStmt(
            new Node\Stmt\ClassConst(
                [
                    new Node\Const_(
                        'SCHEMA_DESCRIPTION',
                        new Node\Scalar\String_(
                            $schema->description ?? ''
                        )
                    ),
                ],
                Class_::MODIFIER_PUBLIC
            )
        );

        foreach ($schema->properties as $propertyName => $property) {
            $propertyStmt = $factory->property($propertyName)->makePrivate();
            $docBlock = [];
            if (strlen($property->description) > 0) {
                $docBlock[] = $property->description;
            }
            $method = $factory->method($propertyName)->makePublic()/*->setReturnType('string')*/->addStmt(
                new Node\Stmt\Return_(
                    new Node\Expr\PropertyFetch(
                        new Node\Expr\Variable('this'),
                        $propertyName
                    )
                )
            );
            if (is_string($property->type)) {
                if ($property->type === 'array' && $property->items instanceof OpenAPiSchema && array_key_exists(spl_object_hash($property->items), $schemaClassNameMap)) {
                    $docBlock[] = '@var array<\\' . $namespace . '\\' . $schemaClassNameMap[spl_object_hash($property->items)] . '>';
                }
                $t = str_replace([
                    'integer',
                    'any',
                    'boolean',
                ], [
                    'int',
                    '',
                    'bool',
                ], $property->type);
                if ($t !== '') {
                    $dft = [];
                    if ($t !== 'array') {
                        $t = '?' . $t;
                        $dft = null;
                    }
                    $propertyStmt->setType($t)->setDefault($dft);
                    $method->setReturnType($t);
                }
            } else if (is_array($property->anyOf) && $property->anyOf[0] instanceof OpenAPiSchema && array_key_exists(spl_object_hash($property->anyOf[0]), $schemaClassNameMap)) {
                $fqcnn = '?\\' . $namespace . '\\' . $schemaClassNameMap[spl_object_hash($property->anyOf[0])];
                $propertyStmt->setType($fqcnn)->setDefault(null);
                $method->setReturnType($fqcnn);
            }

            if (count($docBlock) > 0) {
                $propertyStmt->setDocComment('/**' . PHP_EOL . ' * ' . implode(PHP_EOL . ' * ', $docBlock) . PHP_EOL .' */');
            }

            $class->addStmt($propertyStmt)->addStmt($method);
        }

        return $stmt->addStmt($class)->getNode();
    }
}