<?php

namespace Test\Generic;

trait Box<BoxA,BoxB,BoxC> {

    private ?BoxA $content = null;

    public function setContent(BoxB $content): BoxC {

    }
}
