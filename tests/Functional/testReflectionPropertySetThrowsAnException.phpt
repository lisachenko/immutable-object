--TEST--
Updating property of immutable object via Reflection throws an error
--FILE--
<?php
declare(strict_types=1);

use Immutable\Stub\TestObject;

include __DIR__ . './../bootstrap.php';

$object = new TestObject(['privateProperty' => 200]);
$reflection = new \ReflectionProperty(TestObject::class, 'privateProperty');
$reflection->setAccessible(true);
$reflection->setValue($object, 42);
?>
--EXPECTREGEX--
Immutable object could be modified only in constructor or static methods
