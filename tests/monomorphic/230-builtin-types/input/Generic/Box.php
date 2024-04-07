<?php

namespace Test\Generic;

use Test\Entity\Bird;
use Test\Entity\Cat;

class Box<A,B,C,D,E,F,G,H,I,G,K,L,M,N,O,P> {

    public function test($obj): void {
        var_dump(Container<A,B,C,D,E,F,G,H,I,G,K,L,M,N,O,P>::class);
    }
}
