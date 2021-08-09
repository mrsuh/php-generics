<?php

namespace Test\Generic;

trait BoxTrait<BoxTraitA, BoxTraitB, BoxTraitC> {

    private ?BoxTraitA $content;

    public function setContentA(BoxTraitA $content): ?BoxTraitB {
        $this->content = $content;
    }

    public function getContentB(BoxTraitA $content): ?BoxTraitC {
        $this->content = $content;
    }
}
