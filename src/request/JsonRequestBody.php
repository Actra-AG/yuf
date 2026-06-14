<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\request;

use InvalidArgumentException;
use stdClass;

class JsonRequestBody extends RequestBody
{
    private static ?JsonRequestBody $instance = null;

    private function __construct(
        public readonly stdClass $data
    ) {
    }

    public static function get(): JsonRequestBody
    {
        if (JsonRequestBody::$instance === null) {
            $requestBodyData = RequestBody::getData();
            if ($requestBodyData === '') {
                $requestBodyData = '{}';
            }
            $data = json_decode(json: $requestBodyData);
            $jsonLastError = json_last_error();
            if ($jsonLastError !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException(message: 'JSON error: ' . json_last_error_msg());
            }
            JsonRequestBody::$instance = new JsonRequestBody(data: $data);
        }
        return JsonRequestBody::$instance;
    }

    private function getValue(string $keyName): null|int|float|string|array
    {
        $data = $this->data;
        return property_exists(object_or_class: $data, property: $keyName) ? $data->{$keyName} : null;
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
            return (float)$value;
        }
        throw new InvalidArgumentException(message: 'Invalid JSON property (float): ' . $keyName);
    }

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

    public function getRequiredArray(string $keyName): array
    {
        $value = $this->getOptionalArray(keyName: $keyName);
        if ($value === null || $value === []) {
            throw new InvalidArgumentException(message: 'Missing JSON property (array): ' . $keyName);
        }
        return $value;
    }

}