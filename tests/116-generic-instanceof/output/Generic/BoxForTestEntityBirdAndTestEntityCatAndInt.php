<?php

namespace Test\Generic;

use Test\Entity\Cat;
class BoxForTestEntityBirdAndTestEntityCatAndInt
{
    public function test($obj) : void
    {
        var_dump($obj instanceof \Test\Generic\ContainerForTestEntityBirdAndTestEntityCatAndInt);
    }
}