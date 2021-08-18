<?php

namespace Test\Command;

use Test\Generic\Box;
use Test\Entity\Bird;
use Test\Entity\Cat;

class Usage
{
    public function test(Box<Bird, Cat, int>|int $var1, ?Box<Bird, Cat, int> $va2r, Box<Bird, Cat, int> $var3): void {

    }
}
