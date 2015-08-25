# Hack Scanner

Scan your project for Hack code and generate a mapping of valid fully qualified Hack
names to the files in which the names are defined.

This project is just a thin wrapper around the awesome
[definition-finder](https://github.com/fredemmott/definitions-finder) project by
[Fred Emmott](https://github.com/fredemmott).

## Purpose

This library supplies a simple API for defining which files to scan and how to filter the results.
All of the heavy lifting is done by [definition-finder](https://github.com/fredemmott/definitions-finder).

One primary use case for Hack Scanner is to produce an array suitable to pass to HHVM's built in auto-loader.

Another use case, as seen in [HackUnit](https://github.com/hackpack/hackunit) is to find particular classes
or other definitions in a subtree of your project.  In HackUnit's case, the unit tests must be found because
they are marked with user defined attributes.

## Installation

Use [Composer](https://getcomposer.org/download/) to set this project as a dependency of yours.

```sh
$ composer require --prefer-dist hackpack/hack-scanner
```

The dist version is recommended to prevent type checking errors due to tests using some dev
dependencies that shouldn't be needed for your project.

## Usage

The easiest way to use Hack Scanner is to instantiate the builder and call the appropriate methods for your particular needs.
The builder is configured by calling its public methods.  To obtain an instance of the Scanner class, simply call `getScanner()`
on your instance of the builder.

Below are a couple examples of common use cases.  See below for a reference of all available configuration methods.

```php
<?hh

use HackPack\Scanner\Builder;

// Scan all files recursively searching in the script's parent folder for all names.
$scanner = (new Builder())
    ->addPath(Vector{__DIR__})
    ->includeAll()
    ->getScanner();

// Recursively scan all files in the two paths as well as the file referenced for class definitions only
$scanner = (new Builder())
    ->addPaths(Vector{'/interesting/path/one', '/interesting/path/two', '../relative/path/to/file.php'})
    ->includeAllClasses()
    ->getScanner();
```

### Filters

Sometimes scanning an entire directory tree is not desirable, but referencing each child folder/file would be
quite tedious.  Also you may only want to locate a subset of Classes (for example) defined in a particular set of files.
To accomplish this, you may set a filtering callback on the builder.

```php
<?hh

use HackPack\Scanner\Builder;

// Scan all files except for those in the test or tests subfolder
$scanner = (new Builder())
    ->addPath(__DIR__)
    ->filterFilenames($path ==> ! preg_match('|' . __DIR__ . '/tests?/|', $path))
    ->includeAll()
    ->getScanner();

// Scan all files for classes that have a name matching the regex
$scanner = (new Builder())
    ->addPath(__DIR__)
    ->includeClasses($c ==> preg_match('/^HackPack\\/', $c->getName()))
```

All filter callbacks must have a signature of `function(Scanned*):bool` where `Scanned*` stands for one of the following

* ScannedBasicClass
* ScannedConstant
* ScannedEnum
* ScannedFunction
* ScannedInterface
* ScannedNewtype
* ScannedTrait
* ScannedType

See [definitions-finder](https://github.com/fredemmott/definitions-finder) for more information.

## The Scanner Object

Once a scanner has been built, there are two ways you can access the list of names found.
One is a simple `Map<string,string>` that maps the fully qualified name (including namespace) to the file
in which that name is defined.  The other is an array ready for use by `HH\autoload_set_paths()`
see [this comment](https://github.com/facebook/hhvm/blob/master/hphp/runtime/ext/hh/ext_hh.php#L18-L42) for details.

For example, suppose you have a project structure like this
(this example uses the PSR-4 standard, where `My\Namespace` is associated with `project/src`):

```
- project/
| - src/
  | - Contract/
    | - InterfaceA.php
    | - InterfaceB.php
    | - TypeA.php
  | - Impl/
    | - ClassA.php
    | - ClassB.php
    | - EnumA.php
| - test/
  | - TestThing1.php
  | - TestThing2.php
```

You may build a scanner object and obtain a flat list of all classes/interfaces, or an array ready to configure the autoloader.

```php

$scanner = (new Builder())
    // Scan the entire project
    ->addPath('/path/to/project')
    // Except for the test folder
    ->filterFilenames($n ==> substr($n, 'project/test/') === false)
    ->includeAll()
    ->getScanner();

$scanner->mapNamesToFiles() === Map{
    'My\Namespace\Contract\InterfaceA' => '/path/to/project/src/Contract/InterfaceA.php',
    'My\Namespace\Contract\InterfaceB' => '/path/to/project/src/Contract/InterfaceB.php',
    'My\Namespace\Contract\TypeA' => '/path/to/project/src/Contract/TypeA.php',
    'My\Namespace\Impl\ClassA' => '/path/to/project/src/Impl/ClassA.php',
    'My\Namespace\Impl\ClassB' => '/path/to/project/src/Impl/ClassB.php',
    'My\Namespace\Impl\EnumA' => '/path/to/project/src/Impl/EnumA.php',
}; // true

$scanner->getAutoloadArray() === [
    'class' => [
        'My\Namespace\Contract\InterfaceA' => '/path/to/project/src/Contract/InterfaceA.php',
        'My\Namespace\Contract\InterfaceB' => '/path/to/project/src/Contract/InterfaceB.php',
        'My\Namespace\Impl\ClassA' => '/path/to/project/src/Impl/ClassA.php',
        'My\Namespace\Impl\ClassB' => '/path/to/project/src/Impl/ClassB.php',
        'My\Namespace\Impl\EnumA' => '/path/to/project/src/Impl/EnumA.php',
    ],
    'constant' => [],
    'function' => [],
    'type' => [
        'My\Namespace\Contract\TypeA' => '/path/to/project/src/Contract/TypeA.php',
    ],
]; // true
```

## Reference

### Builder API

#### Filenames

The following two methods add to the list of base paths to be scanned.  Note that all base paths are
scanned recursively.  If you wish to exclude a subdirectory of a base path, use the filename filter (below).
```php
public function addPath(string $path): this
public function addPaths(Traversable<string> $paths): this
```
The following method registers a filename filter with the builder.  For a file to be loaded and scanned,
all registered filters must return `true` when passed the full path to the file.  Filename filters are useful
for excluding a subdirectory of a base path.
```php
public function filterFilenames((function(string):bool) $filter): this
```

#### Including definitions
The following methods instruct the scanner to include the referenced definition type (class, interface, enum, etc.).
The methods may be called many times, where each time an inclusionary filter callback is registered.  For a
definition to be listed, at least one of the registered inclusionary callbacks must return true.

Note that the default callback will allow any definition, making a simple `$builder->includeClasses();` call
include all class definitions.
```php
public function includeClasses((function(ScannedBasicClass):bool = $x ==> true) $filter): this
public function includeConstants((function(ScannedConstant):bool) $filter = $x ==> true): this
public function includeEnums((function(ScannedEnum):bool) $filter = $x ==> true): this
public function includeFunctions((function(ScannedFunction):bool) $filter = $x ==> true): this
public function includeInterfaces((function(ScannedInterface):bool) $filter = $x ==> true): this
public function includeNewtypes((function(ScannedNewtype):bool) $filter = $x ==> true): this
public function includeTraits((function(ScannedTrait):bool) $filter = $x ==> true): this
public function includeTypes((function(ScannedType):bool) $filter = $x ==> true): this
```

#### Excluding definitions
The following methods register filter callbacks for the referenced definition type (class, interface, enum, etc.).
The methods may be called many times, where each time an exclusionary filter callback is registered.  For a
definition to be listed, all registered filters must return true.
```php
public function filterClasses((function(ScannedBasicClass):bool) $filter): this
public function filterConstants((function(ScannedConstant):bool) $filter): this
public function filterEnums((function(ScannedEnum):bool) $filter): this
public function filterFunctions((function(ScannedFunction):bool) $filter): this
public function filterInterfaces((function(ScannedInterface):bool) $filter): this
public function filterNewtypes((function(ScannedNewtype):bool) $filter): this
public function filterTraits((function(ScannedTrait):bool) $filter): this
public function filterTypes((function(ScannedType):bool) $filter): this
```
