<?php

namespace Mrsuh\PhpGenerics\Compiler;

use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinderInterface;
use PhpParser\Node\GenericParameter;

class GenericParametersMap
{
    private array                $parameters = [];
    private ClassFinderInterface $classFinder;

    public function __construct(ClassFinderInterface $classFinder)
    {
        $this->classFinder = $classFinder;
    }

    /**
     * @param GenericParameter[] $parameters
     * @param string[]           $arguments
     */
    public static function fromParametersAndArguments(ClassFinderInterface $classFinder, array $parameters, array $arguments): self
    {
        $map = new self($classFinder);

        if (count($arguments) > count($parameters)) {
            throw new \TypeError(sprintf('Arguments count "%d" is bigger than parameters count "%d"', count($arguments), count($parameters)));
        }

        foreach ($parameters as $index => $genericParameter) {
            $genericParameterName = Parser::getNodeName($genericParameter->name, $classFinder);

            if (isset($arguments[$index])) {
                $concreteArgument = $arguments[$index];
            } else {
                $default = $genericParameter->default;
                if ($default === null) {
                    throw new \TypeError(sprintf('There is no default value for argument "%d"', $index + 1));
                }
                $concreteArgument = Parser::getNodeName($default, $classFinder);
            }

            if (empty($concreteArgument)) {
                throw new \TypeError(sprintf('Invalid argument %d', $index + 1));
            }

            $map->set($genericParameterName, $concreteArgument);
        }

        return $map;
    }

    /**
     * @param GenericParameter[] $genericParameters
     * @return string[]
     */
    public function generateFullArgumentsForNewGenericClass(array $genericParameters): array
    {
        $genericArguments = [];
        foreach ($genericParameters as $genericParameter) {
            if ($genericParameter->name->hasAttribute('originalName')) {
                $name = (string)$genericParameter->name->getAttribute('originalName');
            } else {
                $name = $genericParameter->name->toString();
            }

            if (Parser::isBuiltinType($name)) {
                $genericArguments[] = $name;
                continue;
            }

            if ($this->has($name)) {
                $genericArguments[] = $this->get($name);
                continue;
            }

            $genericArguments[] = Parser::getNodeName($genericParameter->name, $this->classFinder);
        }

        return $genericArguments;
    }

    public function set(string $parameter, string $argument): void
    {
        $this->parameters[$parameter] = $argument;
    }

    public function get(string $parameter): string
    {
        return $this->parameters[$parameter] ?? '';
    }

    public function has(string $parameter): bool
    {
        return isset($this->parameters[$parameter]);
    }

    public function getConcreteArguments(): array
    {
        return array_values($this->parameters);
    }

    public function count(): int
    {
        return count($this->parameters);
    }

    public function all(): array
    {
        return $this->parameters;
    }
}
