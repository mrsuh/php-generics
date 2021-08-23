<?php

namespace Test\Generic;

use Test\Entity\Bird;
use Test\Entity\Cat;

class Box<BoxA = Bird,BoxB = Cat,BoxC = int> {

    public function test($obj): void {
        var_dump(Container<BoxA, Cat, int>::class);
    }
}
