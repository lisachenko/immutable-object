--TEST--
Updating property of immutable object with setter throws an error
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
$object->setPrivateTo(100500);
?>
--EXPECTREGEX--
Immutable object could be modified only in constructor or static methods
