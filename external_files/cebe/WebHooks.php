<?php

/**
 * @copyright Copyright (c) 2021 Carsten Brandt <mail@cebe.cc> and contributors
 * @license https://github.com/cebe/php-openapi/blob/master/LICENSE
 */

namespace cebe\openapi\spec;

use ArrayAccess;
use ArrayIterator;
use cebe\openapi\DocumentContextInterface;
use cebe\openapi\exceptions\TypeErrorException;
use cebe\openapi\exceptions\UnresolvableReferenceException;
use cebe\openapi\json\JsonPointer;
use cebe\openapi\ReferenceContext;
use cebe\openapi\SpecObjectInterface;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Holds the webhook events to the individual endpoints and their operations.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/main/versions/3.1.0.md#oasWebhooks
 *
 */
class WebHooks implements SpecObjectInterface, DocumentContextInterface, ArrayAccess, Countable, IteratorAggregate
{
    /**
     * @var (PathItem|null)[]
     */
    private $_webHooks = [];
    /**
     * @var array
     */
    private $_errors = [];
    /**
     * @var SpecObjectInterface|null
     */
    private $_baseDocument;
    /**
     * @var JsonPointer|null
     */
    private $_jsonPointer;


    /**
     * Create an object from spec data.
     * @param (PathItem|array|null)[] $data spec data read from YAML or JSON
     * @throws TypeErrorException in case invalid data is supplied.
     */
    public function __construct(array $data)
    {
        foreach ($data as $path => $object) {
            if ($object === null) {
                $this->_webHooks[$path] = null;
            } elseif (is_array($object)) {
                $this->_webHooks[$path] = new PathItem($object);
            } elseif ($object instanceof PathItem) {
                $this->_webHooks[$path] = $object;
            } else {
                $givenType = gettype($object);
                if ($givenType === 'object') {
                    $givenType = get_class($object);
                }
                throw new TypeErrorException(sprintf('Path MUST be either array or PathItem object, "%s" given', $givenType));
            }
        }
    }

    /**
     * @return mixed returns the serializable data of this object for converting it
     * to JSON or YAML.
     */
    public function getSerializableData()
    {
        $data = [];
        foreach ($this->_webHooks as $path => $pathItem) {
            $data[$path] = ($pathItem === null) ? null : $pathItem->getSerializableData();
        }
        return (object) $data;
    }

    /**
     * @param string $name path name
     * @return bool
     */
    public function hasWebHook(string $name): bool
    {
        return isset($this->_webHooks[$name]);
    }

    /**
     * @param string $name path name
     * @return PathItem
     */
    public function getWebHook(string $name): ?PathItem
    {
        return $this->_webHooks[$name] ?? null;
    }

    /**
     * @param string $name path name
     * @param PathItem $pathItem the path item to add
     */
    public function addWebHook(string $name, PathItem $pathItem): void
    {
        $this->_webHooks[$name] = $pathItem;
    }

    /**
     * @param string $name path name
     */
    public function removeWebHook(string $name): void
    {
        unset($this->_webHooks[$name]);
    }

    /**
     * @return PathItem[]
     */
    public function getWebHooks(): array
    {
        return $this->_webHooks;
    }

    /**
     * Validate object data according to OpenAPI spec.
     * @return bool whether the loaded data is valid according to OpenAPI spec
     * @see getErrors()
     */
    public function validate(): bool
    {
        $valid = true;
        $this->_errors = [];
        foreach ($this->_webHooks as $key => $path) {
            if ($path === null) {
                continue;
            }
            if (!$path->validate()) {
                $valid = false;
            }
        }
        return $valid && empty($this->_errors);
    }

    /**
     * @return string[] list of validation errors according to OpenAPI spec.
     * @see validate()
     */
    public function getErrors(): array
    {
        if (($pos = $this->getDocumentPosition()) !== null) {
            $errors = [
                array_map(function ($e) use ($pos) {
                    return "[{$pos}] $e";
                }, $this->_errors)
            ];
        } else {
            $errors = [$this->_errors];
        }

        foreach ($this->_webHooks as $path) {
            if ($path === null) {
                continue;
            }
            $errors[] = $path->getErrors();
        }
        return array_merge(...$errors);
    }

    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset An offset to check for.
     * @return boolean true on success or false on failure.
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset)
    {
        return $this->hasWebHook($offset);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset The offset to retrieve.
     * @return PathItem Can return all value types.
     */
    public function offsetGet($offset)
    {
        return $this->getWebHook($offset);
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value The value to set.
     */
    public function offsetSet($offset, $value)
    {
        $this->addWebHook($offset, $value);
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset The offset to unset.
     */
    public function offsetUnset($offset)
    {
        $this->removeWebHook($offset);
    }

    /**
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * The return value is cast to an integer.
     */
    public function count()
    {
        return count($this->_webHooks);
    }

    /**
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing <b>Iterator</b> or <b>Traversable</b>
     */
    public function getIterator()
    {
        return new ArrayIterator($this->_webHooks);
    }

    /**
     * Resolves all Reference Objects in this object and replaces them with their resolution.
     * @throws UnresolvableReferenceException
     */
    public function resolveReferences(ReferenceContext $context = null)
    {
        foreach ($this->_webHooks as $key => $path) {
            if ($path === null) {
                continue;
            }
            $path->resolveReferences($context);
        }
    }

    /**
     * Set context for all Reference Objects in this object.
     */
    public function setReferenceContext(ReferenceContext $context)
    {
        foreach ($this->_webHooks as $key => $path) {
            if ($path === null) {
                continue;
            }
            $path->setReferenceContext($context);
        }
    }

    /**
     * Provide context information to the object.
     *
     * Context information contains a reference to the base object where it is contained in
     * as well as a JSON pointer to its position.
     * @param SpecObjectInterface $baseDocument
     * @param JsonPointer $jsonPointer
     */
    public function setDocumentContext(SpecObjectInterface $baseDocument, JsonPointer $jsonPointer)
    {
        $this->_baseDocument = $baseDocument;
        $this->_jsonPointer = $jsonPointer;

        foreach ($this->_webHooks as $key => $path) {
            if ($path instanceof DocumentContextInterface) {
                $path->setDocumentContext($baseDocument, $jsonPointer->append($key));
            }
        }
    }

    /**
     * @return SpecObjectInterface|null returns the base document where this object is located in.
     * Returns `null` if no context information was provided by [[setDocumentContext]].
     */
    public function getBaseDocument(): ?SpecObjectInterface
    {
        return $this->_baseDocument;
    }

    /**
     * @return JsonPointer|null returns a JSON pointer describing the position of this object in the base document.
     * Returns `null` if no context information was provided by [[setDocumentContext]].
     */
    public function getDocumentPosition(): ?JsonPointer
    {
        return $this->_jsonPointer;
    }
}
