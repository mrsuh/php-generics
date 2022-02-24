<?php

namespace Test\Generic;

class Container<ContainerA,ContainerB,ContainerC>  {

    private ?ContainerA $content = null;

    public function setContent(ContainerB $content): ContainerC {

    }
}
