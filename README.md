# PHP generics written in PHP

![](https://github.com/mrsuh/php-generics/actions/workflows/tests.yml/badge.svg)
![](https://img.shields.io/github/license/mrsuh/php-generics.svg)
![](https://img.shields.io/github/v/release/mrsuh/php-generics)

## Table of contents

* [How it works](#how-it-works)
* [Installation](#installation)
* [Monomorphization](#monomorphization)
* [Type erasure](#type-erasure)
* [Features](#features)
* [Tests](#tests)

## How it works

In a nutshell:
+ parse generics classes;
+ generate concrete classes based on them (you can choose `monomorphization` or `type-erasure`);
+ autoload concrete classes instead of generics classes.

For example, you need to add several PHP files:
+ generic class `Box`;
+ class `Usage` for use generic class;
+ script with composer autoload and `Usage` class.

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

bin/test.php
```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Usage;

$usage = new Usage();
$usage->run();
```

Generate concrete classes from generic classes with `composer dump-generics` command
```bash
composer dump-generics -vv
```

What the `composer dump-generics` command does?
+ finds all generic uses in classes (`src/Usage.php` for example).
+ generates concrete classes from generic classes with unique names based on name and arguments of generic class.
+ replaces generic class names to concrete class names in places of use.

In this case should be generated:
+ 2 concrete classes of generics `BoxForInt` and `BoxForString`;
+ 1 concrete class `Usage` with replaced generics class names to concrete class names.

Generate vendor/autoload.php with `composer dump-autoload` command
```bash
composer dump-autoload
```

Run bin/test.php script
```bash
php bin/test.php
```

Composer autoload first checks the "cache" directory and then the "src" directory to load the classes.

:blue_book: You can find repository with this example [here](https://github.com/mrsuh/php-generics-example).

## Installation

#### Require
* PHP >= 7.4
* Composer (PSR-4 Autoload)

Install library
```bash
composer require mrsuh/php-generics
```

Add directory(`"cache/"`) to composer autoload PSR-4 for generated classes. It should be placed before the main directory.

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

## Monomorphization

A new class is generated for each generic argument combination.

Before `monomorphization`:
```php
<?php

namespace App;

class Box<T> {

    private ?T $data = null;

    public function set(T $data): void
    {
        $this->data = $data;
    }

    public function get(): ?T
    {
        return $this->data;
    }
}
```

After `monomorphization`:
```php
<?php

namespace App;

class BoxForInt {

    private ?int $data = null;
    
    public function set(int $data) : void
    {
        $this->data = $data;
    }
    
    public function get() : ?int
    {
        return $this->data;
    }
}
```
#### Command
```bash
composer dump-generics
```

#### Where in class can generics be used?
```php
<?php

namespace App;

use App\Entity\Cat;
use App\Entity\Bird;
use App\Entity\Dog;

class Test extends GenericClass<Cat> implements GenericInterface<Bird> { // <-- extends/implements
 
  use GenericTrait<Dog>; // <-- trait use
 
  private GenericClass<int>|GenericClass<Dog> $var; // <-- property type
 
  public function test(GenericInterface<int>|GenericInterface<Dog> $var): GenericClass<string>|GenericClass<Bird> { // <-- method argument/return type
      
       var_dump($var instanceof GenericInterface<int>); // <-- instanceof
      
       var_dump(GenericClass<int>::class); // <-- class constants      
       var_dump(GenericClass<array>::CONSTANT); // <-- class constants
      
       return new GenericClass<float>(); // <-- new
  }
}
```

#### Where in generic class can parameters be used?
```php
<?php

namespace App;

class Test<T,V> extends GenericClass<T> implements GenericInterface<V> { // <-- extends/implements
 
  use GenericTrait<T>; // <-- trait use
  use T; // <-- trait use
 
  private T|GenericClass<V> $var; // <-- property type
 
  public function test(T|GenericInterface<V> $var): T|GenericClass<V> { // <-- method argument/return type
      
       var_dump($var instanceof GenericInterface<V>); // <-- instanceof      
       var_dump($var instanceof T); // <-- instanceof
      
       var_dump(GenericClass<T>::class); // <-- class constants   
       var_dump(T::class); // <-- class constants
       var_dump(GenericClass<T>::CONSTANT); // <-- class constants
       var_dump(T::CONSTANT); // <-- class constants
      
       $obj1 = new T(); // <-- new
       $obj2 = new GenericClass<V>(); // <-- new
      
       return $obj2;
  }
}
```

:blue_book: You can read more about `monomorphization` [here](https://dev.to/mrsuh/generics-implementation-approaches-3bf0).

## Type erasure

A new class is generated without generics arguments.

Before `type erasure`:
```php
<?php

namespace App;

class Box<T> {

    private ?T $data = null;

    public function set(T $data): void
    {
        $this->data = $data;
    }

    public function get(): ?T
    {
        return $this->data;
    }
}
```

After `type erasure`:
```php
<?php

namespace App;

class Box {

    private $data = null;
    
    public function set($data) : void
    {
        $this->data = $data;
    }
    
    public function get()
    {
        return $this->data;
    }
}
```

#### Command
```bash
composer dump-generics --type=type-erasure
```

#### Where in class can generics be used?
```php
<?php

namespace App;

use App\Entity\Cat;
use App\Entity\Bird;
use App\Entity\Dog;

class Test extends GenericClass<Cat> implements GenericInterface<Bird> { // <-- extends/implements
 
  use GenericTrait<Dog>; // <-- trait use
 
  private GenericClass<int>|GenericClass<Dog> $var; // <-- property type
 
  public function test(GenericInterface<int>|GenericInterface<Dog> $var): GenericClass<string>|GenericClass<Bird> { // <-- method argument/return type
      
       var_dump($var instanceof GenericInterface<int>); // <-- instanceof
      
       var_dump(GenericClass<int>::class); // <-- class constants      
       var_dump(GenericClass<array>::CONSTANT); // <-- class constants
      
       return new GenericClass<float>(); // <-- new
  }
}
```

#### Where in generic class can parameters be used?
```php
<?php

namespace App;

class Test<T,V> extends GenericClass<T> implements GenericInterface<V> { // <-- extends/implements
 
  use GenericTrait<T>; // <-- trait use
 
  private GenericClass<V> $var; // <-- property type
 
  public function test(T|GenericInterface<V> $var): T|GenericClass<V> { // <-- method argument/return type
      
       var_dump($var instanceof GenericInterface<V>); // <-- instanceof          
      
       var_dump(GenericClass<T>::class); // <-- class constants           
       var_dump(GenericClass<T>::CONSTANT); // <-- class constants      
      
       return new GenericClass<V>(); // <-- new           
  }
}
```

:blue_book: You can read more about `type-erasure` [here](https://dev.to/mrsuh/generics-implementation-approaches-3bf0).

## Features

#### What syntax is used?

The [RFC](https://github.com/PHPGenerics/php-generics-rfc) does not define a specific syntax so i took [this one](https://github.com/PHPGenerics/php-generics-rfc/issues/45) implemented by Nikita Popov

Syntax example:
```php
<?php

namespace App;

class Generic<in T: Iface = int, out V: Iface = string> {
  
   public function test(T $var): V {
  
   }
}
```

#### Syntax problems

I had to upgrade [nikic/php-parser](https://github.com/nikic/PHP-Parser) for parse code with new syntax.<br>
You can see [here](https://github.com/mrsuh/PHP-Parser/pull/1/files#diff-14ec37995c001c0c9808ab73668d64db5d1acc1ab0f60a360dcb9c611ecd57ea) the grammar changes that had to be made for support generics.

Parser use [PHP implementation](https://github.com/ircmaxell/PHP-Yacc) of [YACC](https://wikipedia.org/wiki/Yacc). <br>
The YACC([LALR](https://wikipedia.org/wiki/LALR(1))) algorithm and current PHP syntax make it impossible to describe the full syntax of generics due to collisions.

Collision example:
```php
<?php

const FOO = 'FOO';
const BAR = 'BAR';

var_dump(new \DateTime<FOO,BAR>('now')); // is it generic?
var_dump( (new \DateTime < FOO) , ( BAR > 'now') ); // no, it is not
```

[Solution options](https://github.com/PHPGenerics/php-generics-rfc/issues/35#issuecomment-571546650)

Therefore, nested generics are not currently supported.
```php
<?php

namespace App;

class Usage {
   public function run() {
       $map = new Map<Key<int>, Value<string>>();//not supported
   }
}
```

#### Parameter names have not special restrictions

```php
<?php

namespace App;

class GenericClass<T, varType, myCoolLongParaterName> {
   private T $var1;
   private varType $var2;
   private myCoolLongParaterName $var3;   
}
```

#### Several generic parameters support

```php
<?php

namespace App;

class Map<keyType, valueType> {
  
   private array $map;
  
   public function set(keyType $key, valueType $value): void {
       $this->map[$key] = $value;
   }
  
   public function get(keyType $key): ?valueType {
       return $this->map[$key] ?? null;
   }
}
```

#### Default generic parameter support

```php
<?php

namespace App;

class Map<keyType = string, valueType = int> {
  
   private array $map = [];
  
   public function set(keyType $key, valueType $value): void {
       $this->map[$key] = $value;
   }
  
   public function get(keyType $key): ?valueType {
       return $this->map[$key] ?? null;
   }
}
```

```php
<?php

namespace App;

class Usage {
   public function run() {
       $map = new Map<>();//be sure to add "<>"
       $map->set('key', 1);
       var_dump($map->get('key'));
   }
}
```

#### How fast is it?

All concrete classes are pre-generated and can be cached(should not affect performance).

Generating many concrete classes should negatively impact performance when:
+ resolves concrete classes;
+ storing concrete classes in memory;
+ type checking for each concrete class.

I think it's all individual for a specific case.

#### Doesn't work without composer autoload

Autoload magic of concrete classes works with composer autoload only. <br>
Nothing will work because of syntax error if you include file by "require"

#### Reflection

PHP does type checks in [runtime](https://github.com/PHPGenerics/php-generics-rfc/issues/43). <br>
Therefore, all generics arguments [must me available](https://github.com/PHPGenerics/php-generics-rfc/blob/cc7219792a5b35226129d09536789afe20eac029/generics.txt#L426-L430) through reflection in runtime. <br>
It can't be, because information about generics arguments is erased after concrete classes are generated.

## Tests

### How to run tests?

```bash
composer test
```

### How to add test?

+ Add directory 00-your-dir-name to ./tests/{monomorphic/type-erased}
+ Generate output files and check it
```bash
php bin/generate.php monomorphic tests/monomorphic/000-your-dir-name
php bin/generate.php type-erased tests/type-erased/000-your-dir-name
```
