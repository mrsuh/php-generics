<?php

namespace Test\Command;

use Test\Generic\Box;
use Test\Entity\Bird;
use Test\Entity\Cat;

class Usage
{
    use Box<Bird, Cat, int>;
}
