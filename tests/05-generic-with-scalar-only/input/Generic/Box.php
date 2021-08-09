<?php

namespace Test\Generic;

class Box<BoxA,BoxB,BoxC> implements BoxInterface<string,int,array> {

    use BoxTrait<string,int,array>;

    private ?BoxA $content;

    public function setContent(BoxB $content): BoxC {
        $this->content = $content;
    }
}
