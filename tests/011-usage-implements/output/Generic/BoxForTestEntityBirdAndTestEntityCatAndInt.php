<?php

namespace Test\Generic;

interface BoxForTestEntityBirdAndTestEntityCatAndInt
{
    public function setContentA(\Test\Entity\Bird $content) : int;
    public function setContentB(\Test\Entity\Cat $content) : int;
}