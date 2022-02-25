<?php

namespace Test;

class Box extends \Test\GenericClass implements \Test\GenericInterface
{
    use \Test\GenericTrait;
    private \Test\GenericClass|int|string $var;
    public function test(\Test\GenericInterface|int|string $var) : \Test\GenericClass|int|string
    {
        var_dump($var instanceof \Test\GenericInterface);
        var_dump(\Test\GenericClass::class);
        var_dump(\Test\GenericClass::CONSTANT);
        return new \Test\GenericClass();
    }
}