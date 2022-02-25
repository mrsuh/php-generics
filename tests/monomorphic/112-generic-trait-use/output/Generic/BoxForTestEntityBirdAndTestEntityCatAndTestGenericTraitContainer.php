<?php

namespace Test\Generic;

use Test\Entity\Cat;
class BoxForTestEntityBirdAndTestEntityCatAndTestGenericTraitContainer
{
    use \Test\Generic\ContainerForTestEntityBirdAndTestEntityCatAndInt;
    use \Test\Generic\TraitContainer;
}