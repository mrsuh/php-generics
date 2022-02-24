<?php

namespace Test\Generic;

interface Box<BoxA,BoxB,BoxC> {

    public function setContentA(BoxA $content): BoxC;

    public function setContentB(BoxB $content): BoxC;
}
