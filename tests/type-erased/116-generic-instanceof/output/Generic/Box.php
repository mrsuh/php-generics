<?php

namespace Test\Generic;

use Test\Entity\Cat;
class Box
{
    public function test($obj) : void
    {
        var_dump($obj instanceof \Test\Generic\Container);
    }
}