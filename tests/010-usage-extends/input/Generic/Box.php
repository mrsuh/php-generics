<?php

namespace Test\Generic;

class Box<BoxA,BoxB,BoxC> {

    private ?BoxA $content = null;

    public function setContent(BoxB $content): BoxC {

    }
}
