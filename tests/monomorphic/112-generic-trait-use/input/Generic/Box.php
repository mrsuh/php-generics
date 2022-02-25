<?php

namespace Test\Generic;

use Test\Entity\Cat;

class Box<BoxA,BoxB,BoxC> {
    use Container<BoxA, Cat, int>;
    use BoxC;
}
