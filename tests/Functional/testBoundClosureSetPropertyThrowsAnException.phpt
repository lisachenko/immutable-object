--TEST--
Updating property of immutable object via Bound closure throws an error
--INI--
ffi.enable=1
opcache.jit=off
opcache.enable_cli=0
--FILE--
<?php
declare(strict_types=1);

use Immutable\Stub\TestObject;

include __DIR__ . './../bootstrap.php';

$object = new TestObject(['privateProperty' => 200]);
$closure = function (object $instance, string $propertyName, $newValue) {
    $instance->$propertyName = $newValue;
};
$boundClosure = $closure->bindTo($object, TestObject::class);
$boundClosure($object, 'privateProperty', 42);
?>
--EXPECTREGEX--
Immutable object could be modified only in constructor or static methods
