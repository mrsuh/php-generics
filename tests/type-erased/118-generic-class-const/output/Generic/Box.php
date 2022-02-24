<?php

namespace Test\Generic;

use Test\Entity\Cat;
class Box
{
    public function test($obj) : void
    {
        var_dump(\Test\Generic\Container::class);
    }
}