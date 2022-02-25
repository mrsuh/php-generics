<?php

namespace Test\Generic;

interface ContainerForTestEntityBirdAndTestEntityCatAndInt
{
    public function setContentA(\Test\Entity\Cat $content) : int;
    public function setContentA(\Test\Entity\Bird $content) : int;
}