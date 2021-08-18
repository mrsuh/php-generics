<?php

namespace Test\Command;

use Test\Generic\Box;
use Test\Entity\Bird;
use Test\Entity\Cat;
class Usage
{
    public function test() : void
    {
        var_dump(\Test\Generic\BoxForTestEntityBirdAndTestEntityCatAndInt::class);
    }
}