<?php

namespace Test\Command;

use Test\Generic\Box;

class GenericWithScalar
{
    public function exec()
    {
        var_dump(Box<Bird, Cat, Dog>::class);
    }
}
