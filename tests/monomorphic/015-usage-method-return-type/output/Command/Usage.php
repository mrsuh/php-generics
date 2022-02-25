<?php

namespace Test\Command;

use Test\Generic\Box;
use Test\Entity\Bird;
use Test\Entity\Cat;
class Usage
{
    public function test1() : \Test\Generic\BoxForTestEntityBirdAndTestEntityCatAndInt|int
    {
    }
    public function test2() : ?\Test\Generic\BoxForTestEntityBirdAndTestEntityCatAndInt
    {
    }
    public function test3() : \Test\Generic\BoxForTestEntityBirdAndTestEntityCatAndInt
    {
    }
}