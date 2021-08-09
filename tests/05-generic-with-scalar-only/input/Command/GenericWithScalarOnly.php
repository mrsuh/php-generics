<?php

namespace Test\Command;

use Test\Entity\Bird;
use Test\Entity\Cat;
use Test\Entity\Dog;
use Test\Generic\Box;

class GenericWithScalarOnly
{
    public function exec()
    {
        var_dump(Box<Bird, Cat, Dog>::class);
    }
}
