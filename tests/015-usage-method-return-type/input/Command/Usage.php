<?php

namespace Test\Command;

use Test\Generic\Box;
use Test\Entity\Bird;
use Test\Entity\Cat;

class Usage
{
    public function test1(): Box<Bird, Cat, int>|int {

    }

    public function test2(): ?Box<Bird, Cat, int> {

    }

    public function test3(): Box<Bird, Cat, int> {

    }
}
