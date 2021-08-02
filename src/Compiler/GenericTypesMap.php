<?php

namespace Mrsuh\PhpGenerics\Compiler;

use PhpParser\Node\GenericParameter;

class GenericTypesMap
{
    private array $types = [];

    /**
     * @param GenericParameter[] $parameters
     * @param string[]           $arguments
     * @return static
     */
    public static function fromParametersAndArguments(ClassFinderInterface $classFinder, array $parameters, array $arguments): self
    {
        $map = new self();

        if (count($arguments) > count($parameters)) {
            throw new \TypeError('Invalid types count');
        }

        foreach ($parameters as $index => $genericParameter) {
            $genericParameterName = Parser::getNodeName($genericParameter->name, $classFinder);

            if (isset($arguments[$index])) {
                $concreteType = $arguments[$index];
            } else {
                $default = $genericParameter->default;
                if ($default === null) {
                    throw new \TypeError('There is no default value for index ' . $index);//@todo
                }
                $concreteType = Parser::getNodeName($default, $classFinder);
            }

            if (empty($concreteType)) {
                throw new \TypeError('Empty type');//@todo
            }

            $map->set($genericParameterName, $concreteType);
        }

        return $map;
    }

    public function set(string $type, string $concreteType): void
    {
        $this->types[$type] = $concreteType;
    }

    public function get(string $type): string
    {
        return $this->types[$type] ?? '';
    }

    public function has(string $type): bool
    {
        return isset($this->types[$type]);
    }

    public function getConcreteTypes(): array
    {
        return array_values($this->types);
    }

    public function count(): int
    {
        return count($this->types);
    }

    public function all(): array
    {
        return $this->types;
    }
}
