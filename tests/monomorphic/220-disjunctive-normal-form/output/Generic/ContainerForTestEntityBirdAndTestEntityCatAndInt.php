<?php

namespace Test\Generic;

class ContainerForTestEntityBirdAndTestEntityCatAndInt
{
    private readonly null|(\Test\Entity\Bird&\Test\Entity\Cat)|int|false|true $content = null;
    //@todo https://github.com/nikic/PHP-Parser/issues/910
    public function setContent((\Test\Entity\Bird&\Test\Entity\Cat)|int|null|false|true $content): (\Test\Entity\Bird&\Test\Entity\Cat)|int|null|false|true
    {
    }
}