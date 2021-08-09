<?php

namespace Test\Generic;

use Test\Entity\Data;

class Box<BoxA,BoxB,BoxC> extends BoxExtends<BoxA,BoxB,BoxC>{

    use BoxTrait<BoxA,int,BoxC>;

    private ?BoxA $content;
    private ?Data<BoxC,BoxB,BoxA> $data;

    public function setContent(BoxB $content): BoxC {
        $this->content = $content;
    }

    public function setData(Data<BoxC,BoxB,BoxA> $content): Data<BoxC,BoxB,BoxA> {
        $this->content = $content;
    }
}
