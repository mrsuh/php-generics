<?php

namespace Test\Generic;

interface Container<ContainerA,ContainerB,ContainerC>  {

    public function setContentA(ContainerB $content): ContainerC;

    public function setContentA(ContainerA $content): ContainerC;
}
