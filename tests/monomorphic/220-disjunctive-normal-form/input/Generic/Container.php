<?php

namespace Test\Generic;

class Container<ContainerA,ContainerB,ContainerC>  {

    private readonly null|(ContainerA&ContainerB)|ContainerC|false|true $content = null;//@todo https://github.com/nikic/PHP-Parser/issues/910

    public function setContent((ContainerA&ContainerB)|ContainerC|null|false|true $content): (ContainerA&ContainerB)|ContainerC|null|false|true {

    }
}
