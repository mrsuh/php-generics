<?php

namespace Test\Command;

use App\Entity\Cat;
use Test\Generic\BoxWithCollection;
use Test\Generic\Box;

class ClearTemplate
{
    public function exec()
    {
        var_dump(Box<int, string, array>::class);
        var_dump(BoxWithCollection<stdClass>::class);
    }
}
