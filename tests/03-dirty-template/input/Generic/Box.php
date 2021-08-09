<?php

namespace Test\Generic;

class Box<BoxA,BoxB,BoxC> implements BoxInterface<BoxA,BoxB,BoxC> {

    use BoxTrait<BoxA,BoxB,BoxC>;

    private ?BoxA $content;

    public function setContent(BoxB $content): BoxC {
        $this->content = $content;
    }
}
