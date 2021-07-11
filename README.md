# Native PHP Generics

## Require
* php >= 7.4
* composer

## Installation
```bash
composer require mrsuh/php-generics
```

## Quick start

Generic.php
```php
<?php

namespace App;

class Box<T> {
    private T $content;
    
    public function setContent(T $content) {
        $this->content = $content;
    }
    
    public function getContent(): T {
        return $this->content;
    }
}
```

Usage.php
```php
<?php

namespace App;

class Usage {
    public function run() {
        $stringBox = new Box<string>();
        $stringBox->setContent('cat'); 
        var_dump($stringBox->getContent()); // string "cat"

        $intBox = new Box<int>(); 
        $intBox->setContent(1);
        var_dump($intBox->getContent()); // integer 1

        $stringBox->setContent(1); // TypeError
    }
}
```

index.php
```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Usage;

$usage = new Usage();
$usage->run();
```

```bash
php index.php
string "cat"
integer 1
TypeError
```
