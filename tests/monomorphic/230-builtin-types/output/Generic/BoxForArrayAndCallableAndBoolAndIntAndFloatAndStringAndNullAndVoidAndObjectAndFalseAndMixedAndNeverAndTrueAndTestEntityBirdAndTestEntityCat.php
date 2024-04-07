<?php

namespace Test\Generic;

use Test\Entity\Bird;
use Test\Entity\Cat;
class BoxForArrayAndCallableAndBoolAndIntAndFloatAndStringAndNullAndVoidAndObjectAndFalseAndMixedAndNeverAndTrueAndTestEntityBirdAndTestEntityCat
{
    public function test($obj): void
    {
        var_dump(\Test\Generic\ContainerForArrayAndCallableAndBoolAndIntAndFloatAndStringAndNullAndVoidAndObjectAndFalseAndMixedAndNeverAndTrueAndTestEntityBirdAndTestEntityCat::class);
    }
}