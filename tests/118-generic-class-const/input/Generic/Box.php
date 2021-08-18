<?php

namespace Test\Generic;

use Test\Entity\Cat;

class Box<BoxA,BoxB,BoxC> {

    public function test($obj): void {
        var_dump(Container<BoxA, Cat, int>::class);
    }
}
