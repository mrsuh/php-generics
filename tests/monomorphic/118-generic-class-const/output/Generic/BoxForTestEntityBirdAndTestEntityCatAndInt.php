<?php

namespace Test\Generic;

use Test\Entity\Cat;
class BoxForTestEntityBirdAndTestEntityCatAndInt
{
    public function test($obj) : void
    {
        var_dump(\Test\Generic\ContainerForTestEntityBirdAndTestEntityCatAndInt::class);
    }
}