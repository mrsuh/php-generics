<?php

namespace Mrsuh\PhpGenerics\Compiler;

class Result
{
    private HandledClass $usageClass;

    /** @var HandledClass[] */
    private array $handledClasses;

    public function addGenericClass(HandledClass $genericClass): void
    {
        $this->handledClasses[] = $genericClass;
    }

    public function setUsageClass(HandledClass $usageClass): void
    {
        $this->usageClass = $usageClass;
    }

    public function getUsageClass(): HandledClass
    {
        return $this->usageClass;
    }

    /**
     * @return HandledClass[]
     */
    public function getGenericClasses(): array
    {
        return $this->handledClasses;
    }
}
