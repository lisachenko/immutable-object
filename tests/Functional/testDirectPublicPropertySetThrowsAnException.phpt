--TEST--
Writing property of immutable object throws an error
--FILE--
<?php
declare(strict_types=1);

use Immutable\Stub\TestObject;

include __DIR__ . './../bootstrap.php';

$object = new TestObject(['publicProperty' => 200]);
$object->publicProperty = 300;
?>
--EXPECTREGEX--
Immutable object could be modified only in constructor or static methods
