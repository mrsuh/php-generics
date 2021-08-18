<?php

namespace Test\Generic;

use Test\Entity\Cat;

class Box<BoxA,BoxB,BoxC> {

    public function test1(): Container<BoxA, Cat, int>|int {

    }

    public function test2(): Container<BoxA, Cat, int> {

    }

    public function test3(): ?Container<BoxA, Cat, int> {

    }
}
