<?php

namespace Test\Generic;

class ContainerForTestEntityBirdAndTestEntityCatAndInt
{
    private readonly (\Test\Entity\Bird&\Test\Entity\Cat)|int|null|false|true $content = null;
    public function setContent((\Test\Entity\Bird&\Test\Entity\Cat)|int|null|false|true $content): (\Test\Entity\Bird&\Test\Entity\Cat)|int|null|false|true
    {
    }
}