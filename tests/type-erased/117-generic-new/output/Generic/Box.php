<?php

namespace Test\Generic;

use Test\Entity\Cat;
class Box
{
    public function test($obj) : void
    {
        return new \Test\Generic\Container();
    }
}