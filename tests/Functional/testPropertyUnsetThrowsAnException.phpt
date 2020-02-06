--TEST--
Writing property of immutable object throws an error
--FILE--
<?php
declare(strict_types=1);

use Immutable\Stub\TestObject;

include __DIR__ . './../bootstrap.php';

$object = new TestObject(['publicProperty' => 200]);
unset($object->publicProperty);
?>
--EXPECTREGEX--
Unset of immutable field is restricted
