<?php

namespace Test\Generic;

class BoxExtends<BoxExtendsA,BoxExtendsB,BoxExtendsC> {

    private ?BoxExtendsA $content;

    public function setContent(BoxExtendsB $content): BoxExtendsC {
        $this->content = $content;
    }
}
