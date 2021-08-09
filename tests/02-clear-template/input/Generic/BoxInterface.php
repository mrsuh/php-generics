<?php

namespace Test\Generic;

interface BoxInterface<BoxInterfaceA, BoxInterfaceB, BoxInterfaceC>
{
    public function setContentA(BoxInterfaceA $content): ?BoxInterfaceB;

    public function getContentB(BoxInterfaceA $content): ?BoxInterfaceC;
}
