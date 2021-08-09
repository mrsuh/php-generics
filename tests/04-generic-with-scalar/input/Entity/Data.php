<?php

namespace Test\Entity;

class Data<DataA,DataB,DataC> {

    private ?DataA $data;

    public function setData(DataB $content): DataC {
        $this->content = $content;
    }
}
