<?php

namespace Test\Generic;

use Test\Entity\Cat;

class Box<BoxA,BoxB,BoxC> {

    private Container<BoxA, Cat, int> $var;
}
