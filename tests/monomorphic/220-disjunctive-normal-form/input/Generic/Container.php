<?php

namespace Test\Generic;

class Container<ContainerA,ContainerB,ContainerC>  {

    private readonly (ContainerA&ContainerB)|ContainerC|null|false|true $content = null;

    public function setContent((ContainerA&ContainerB)|ContainerC|null|false|true $content): (ContainerA&ContainerB)|ContainerC|null|false|true {

    }
}
