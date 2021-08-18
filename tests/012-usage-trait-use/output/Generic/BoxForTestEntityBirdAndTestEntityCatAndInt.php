<?php

namespace Test\Generic;

trait BoxForTestEntityBirdAndTestEntityCatAndInt
{
    private ?\Test\Entity\Bird $content = null;
    public function setContent(\Test\Entity\Cat $content) : int
    {
    }
}