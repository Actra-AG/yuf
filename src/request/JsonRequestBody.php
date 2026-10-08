<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\request;

use InvalidArgumentException;
use stdClass;

/**
 * The JSON object of a request body, read with typed getters (`getRequiredString()`, `getOptionalInteger()`, …).
 */
final readonly class JsonRequestBody
{
    private function __construct(
        public stdClass $data,
    ) {}

    /**
     * An empty string is an empty object.
     *
     * @throws InvalidArgumentException if the string is not valid JSON or not a JSON object
     */
    public static function fromString(string $json): JsonRequestBody
    {
        if ($json === '') {
            $json = '{}';
        }
        $data = json_decode(json: $json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(message: 'JSON error: ' . json_last_error_msg());
        }
        if (!$data instanceof stdClass) {
            throw new InvalidArgumentException(
                message: 'JSON error: the request body must be a JSON object, ' . get_debug_type(value: $data)
                    . ' given',
            );
        }

        return new JsonRequestBody(data: $data);
    }

    /**
     * @return string|int|float|bool|stdClass|list<mixed>|null
     */
    private function getValue(string $keyName): string|int|float|bool|stdClass|array|null
    {
        $data = $this->data;
        if (!property_exists(object_or_class: $data, property: $keyName)) {
            return null;
        }
        /** @var string|int|float|bool|stdClass|list<mixed>|null $value */
        $value = $data->{$keyName};

        return $value;
    }

    public function getRequiredString(string $keyName): string
    {
        $value = $this->getOptionalString(keyName: $keyName);
        if ($value === null || $value === '') {
            throw new InvalidArgumentException(message: 'Missing JSON property (string): ' . $keyName);
        }

        return $value;
    }

    public function getOptionalString(string $keyName): ?string
    {
        $value = $this->getValue(keyName: $keyName);
        if ($value === null) {
            return null;
        }
        if (!is_string(value: $value)) {
            throw new InvalidArgumentException(message: 'Invalid JSON property (string): ' . $keyName);
        }

        return trim(string: $value);
    }

    public function getRequiredInteger(string $keyName): int
    {
        $value = $this->getOptionalInteger(keyName: $keyName);
        if ($value === null) {
            throw new InvalidArgumentException(message: 'Missing JSON property (integer): ' . $keyName);
        }
        return $value;
    }

    public function getOptionalInteger(string $keyName): ?int
    {
        $value = $this->getValue(keyName: $keyName);
        if ($value === null) {
            return null;
        }
        if (is_int(value: $value)) {
            return $value;
        }
        throw new InvalidArgumentException(message: 'Invalid JSON property (integer): ' . $keyName);
    }

    public function getRequiredFloat(string $keyName): float
    {
        $value = $this->getOptionalFloat(keyName: $keyName);
        if ($value === null) {
            throw new InvalidArgumentException(message: 'Missing JSON property (float): ' . $keyName);
        }
        return $value;
    }

    public function getOptionalFloat(string $keyName): ?float
    {
        $value = $this->getValue(keyName: $keyName);
        if ($value === null) {
            return null;
        }
        if (is_float(value: $value)) {
            return $value;
        }
        if (is_int(value: $value)) {
            return (float) $value;
        }
        throw new InvalidArgumentException(message: 'Invalid JSON property (float): ' . $keyName);
    }

    /**
     * @return ?list<mixed>
     */
    public function getOptionalArray(string $keyName): ?array
    {
        $value = $this->getValue(keyName: $keyName);
        if ($value === null) {
            return null;
        }
        if (is_array(value: $value)) {
            return $value;
        }
        throw new InvalidArgumentException(message: 'Invalid JSON property (array): ' . $keyName);
    }

    /**
     * @return list<mixed>
     */
    public function getRequiredArray(string $keyName): array
    {
        $value = $this->getOptionalArray(keyName: $keyName);
        if ($value === null || $value === []) {
            throw new InvalidArgumentException(message: 'Missing JSON property (array): ' . $keyName);
        }
        return $value;
    }
}
