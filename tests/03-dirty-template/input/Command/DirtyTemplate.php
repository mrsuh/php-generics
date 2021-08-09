<?php

namespace Test\Command;

use Test\Generic\Box;
use Test\Generic\BoxExtends;
use Test\Generic\BoxTrait;
use Test\Generic\BoxInterface;

class DirtyTemplate extends BoxExtends<int, string, array> implements BoxInterface<int, string, array>
{
    use BoxTrait<int, string, array>;
    private Box<int, string, array> $box;
    public function test(Box<int, string, array> $box) : Box<int, string, array>
    {
    }
}
