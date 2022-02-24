<?php

namespace Test\Generic;

use Test\Entity\Cat;

class Box<BoxA,BoxB,BoxC> {

    public function test(Container<BoxA, Cat, int>|int $var1, Container<BoxA, Cat, int> $var2, ?Container<BoxA, Cat, int> $var3): void {

    }
}
