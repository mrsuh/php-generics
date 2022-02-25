<?php

namespace Test\Generic;

class BoxForTestEntityBirdAndTestEntityCatAndInt
{
    private ?\Test\Entity\Bird $content = null;
    public function setContent(\Test\Entity\Cat $content) : int
    {
    }
}