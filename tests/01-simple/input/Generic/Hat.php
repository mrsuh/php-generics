<?php

namespace Test\Generic;

class Hat<T >
{
    private T $var;

    public function set(T $var): T
    {
        $this->var = $var;

        return $this->var;
    }
}
