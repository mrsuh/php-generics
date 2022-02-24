<?php

namespace Test\Command;

use Test\Generic\Box;
use Test\Entity\Bird;
use Test\Entity\Cat;

class Usage
{
    private Box<Bird, Cat, int>|int $var1;
    private ?Box<Bird, Cat, int> $va2r;
    private Box<Bird, Cat, int> $var3;
}
