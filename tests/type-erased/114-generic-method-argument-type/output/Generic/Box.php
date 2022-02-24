<?php

namespace Test\Generic;

use Test\Entity\Cat;
class Box
{
    public function test(\Test\Generic\Container|int $var1, \Test\Generic\Container $var2, \Test\Generic\Container $var3) : void
    {
    }
}