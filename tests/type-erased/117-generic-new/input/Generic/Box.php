<?php

namespace Test\Generic;

use Test\Entity\Cat;

class Box<BoxA,BoxB,BoxC> {

    public function test($obj): void {
        return new Container<BoxA, Cat, int>();
    }
}
