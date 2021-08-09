<?php

namespace Test\Command;

use Test\Entity\Cat;
use Test\Generic\Collection;

class Repository
{
    public function findAll(): Collection<Cat>
    {
        return new Collection<Cat>();
    }
}
