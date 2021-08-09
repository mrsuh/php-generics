## Tests

Generate output files and check it
```bash
php bin/generate.php tests/01-simple/input tests/01-simple/output 'Test\'
php bin/generate.php tests/02-clear-template/input tests/02-clear-template/output 'Test\'
php bin/generate.php tests/03-dirty-template/input tests/03-dirty-template/output 'Test\'
php bin/generate.php tests/04-generic-with-scalar/input tests/04-generic-with-scalar/output 'Test\'
php bin/generate.php tests/05-generic-with-scalar-only/input tests/05-generic-with-scalar-only/output 'Test\'
php bin/generate.php tests/06-repository/input tests/06-repository/output 'Test\'
```

Test output files
```bash
php bin/test.php
```
