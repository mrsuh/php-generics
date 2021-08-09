<?php

namespace Test\Command;

use Test\Generic\Hat;

class Test
{
    public function main(): void
    {
        echo Hat<int>::class;
    }
}
