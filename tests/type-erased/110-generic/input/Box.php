<?php

namespace Test;

class Box<T,V> extends GenericClass<T> implements GenericInterface<V> {

    use GenericTrait<T>;

    private GenericClass<V> $var1;
    private ?T $var2;
    private T|int|string $var3;

    public function test(T|GenericInterface<V>|int|string $var): T|GenericClass<V>|int|string {
        var_dump($var instanceof GenericInterface<V>);

        var_dump(GenericClass<T>::class);
        var_dump(GenericClass<T>::CONSTANT);

        return new GenericClass<V>();
    }
}
