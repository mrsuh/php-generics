<?php

namespace Mrsuh\PhpGenerics\Compiler;

class Result
{
    private ConcreteClass $usageClass;

    /** @var ConcreteClass[] */
    private array $handledClasses;

    public function addGenericClass(ConcreteClass $genericClass): void
    {
        $this->handledClasses[] = $genericClass;
    }

    public function setUsageClass(ConcreteClass $usageClass): void
    {
        $this->usageClass = $usageClass;
    }

    public function getUsageClass(): ConcreteClass
    {
        return $this->usageClass;
    }

    /**
     * @return ConcreteClass[]
     */
    public function getGenericClasses(): array
    {
        return $this->handledClasses;
    }
}
