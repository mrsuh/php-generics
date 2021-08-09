<?php

namespace Test\Generic;

class BoxWithCollection<T> {

   public function get(): Collection<T> {
       return new Collection<T>();
    }
}
