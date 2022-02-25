<?php

namespace Test;

class Box extends GenericClass<int> implements GenericInterface<string> {

    use GenericTrait<array>;

    private GenericClass<double>|int|string $var;

    public function test(GenericInterface<int>|int|string $var): GenericClass<int>|int|string {
        var_dump($var instanceof GenericInterface<int>);

        var_dump(GenericClass<int>::class);
        var_dump(GenericClass<int>::CONSTANT);

        return new GenericClass<int>();
    }
}
