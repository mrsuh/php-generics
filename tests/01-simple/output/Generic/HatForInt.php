<?php

namespace Test\Generic;

class HatForInt
{
    private int $var;
    public function set(int $var) : int
    {
        $this->var = $var;
        return $this->var;
    }
}