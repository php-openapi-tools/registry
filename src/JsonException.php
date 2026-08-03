<?php

declare(strict_types=1);

namespace OpenAPITools\Registry;

use function json_last_error;
use function json_last_error_msg;

/** @api */
final class JsonException extends \JsonException
{
    public static function createFromPhpError(): self
    {
        return new self(json_last_error_msg(), json_last_error());
    }
}
