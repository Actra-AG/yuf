<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use LogicException;

final class InputParameterCollection
{
    /** @var array<string, InputParameter> */
    private array $allParameters = [];
    /** @var array<string, InputParameter> */
    private array $requiredParameters = [];
    /** @var array<string, InputParameter> */
    private array $optionalParameters = [];

    public function add(InputParameter $inputParameter): void
    {
        $name = $inputParameter->name;
        if (array_key_exists(key: $name, array: $this->allParameters)) {
            throw new LogicException(message: 'There is already an input parameter with this name: ' . $name);
        }
        $this->allParameters[$name] = $inputParameter;
        if ($inputParameter->isRequired) {
            $this->requiredParameters[$name] = $inputParameter;
        } else {
            $this->optionalParameters[$name] = $inputParameter;
        }
    }

    /**
     * @return array<string, InputParameter>
     */
    public function listAllParameters(): array
    {
        return $this->allParameters;
    }

    /**
     * @return array<string, InputParameter>
     */
    public function listRequiredParameters(): array
    {
        return $this->requiredParameters;
    }

    /**
     * @return array<string, InputParameter>
     */
    public function listOptionalParameters(): array
    {
        return $this->optionalParameters;
    }

    public function findParameter(string $name): ?InputParameter
    {
        return array_key_exists(key: $name, array: $this->allParameters) ? $this->allParameters[$name] : null;
    }

    public function hasParameter(string $name): bool
    {
        return array_key_exists(key: $name, array: $this->allParameters);
    }
}
