<?php

namespace Test\Command;

use Test\Generic\Box;
use Test\Entity\Bird;
use Test\Entity\Cat;
class Usage
{
    public function test($obj) : void
    {
        var_dump($obj instanceof \Test\Generic\BoxForTestEntityBirdAndTestEntityCatAndInt);
    }
}