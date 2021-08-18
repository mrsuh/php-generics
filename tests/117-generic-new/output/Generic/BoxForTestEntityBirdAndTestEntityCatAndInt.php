<?php

namespace Test\Generic;

use Test\Entity\Cat;
class BoxForTestEntityBirdAndTestEntityCatAndInt
{
    public function test($obj) : void
    {
        return new \Test\Generic\ContainerForTestEntityBirdAndTestEntityCatAndInt();
    }
}