# PHP generics written in PHP

## Require

* PHP >= 7.4
* Composer (PSR-4 Autoload)

## Quick start

Install library
```bash
composer require mrsuh/php-generics
```

Add directory for generated files(`"cache/"`) to autoload (directory must be placed before the main directory)
composer.json
```json
{
    "autoload": { 
        "psr-4": {
            "App\\": ["cache/","src/"]
        }
    }
}
```

## Example

Add generic class
src/Box.php
```php
<?php

namespace App;

class Box<T> {

    private ?T $data = null;

    public function set(T $data): void {
        $this->data = $data;
    }

    public function get(): ?T {
        return $this->data;
    }
}
```

Add usage generic class
src/Usage.php
```php
<?php

namespace App;

class Usage {

    public function run(): void
    {
        $stringBox = new Box<string>();
        $stringBox->set('cat');
        var_dump($stringBox->get()); // string "cat"

        $intBox = new Box<int>();
        $intBox->set(1);
        var_dump($intBox->get()); // integer 1
    }
}
```

Add test with Usage class and composer autoload
bin/test.php
```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Usage;

$usage = new Usage();
$usage->run();
```

Generate concrete classes from generics
```bash
composer dump-generics -vv
```

Dump autoload classes
```bash
composer dump-autoload
```

Run bin/test.php script
```bash
php bin/test.php
```

See the [tests](./tests) folder for more examples.

## Tests

### How to run tests?
```bash
php bin/test.php
```

### How to add test?
+ Add directory 00-your-dir-name to ./tests
+ Generate output files and check it
```bash
php bin/generate.php tests/000-your-dir-name/input tests/000-your-dir-name/output 'Test\'
```
+ Test output files
```bash
php bin/test.php
```
