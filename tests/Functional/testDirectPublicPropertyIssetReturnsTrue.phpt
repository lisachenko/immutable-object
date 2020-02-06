--TEST--
Writing property of immutable object throws an error
--FILE--
<?php
declare(strict_types=1);

use Immutable\Stub\TestObject;

include __DIR__ . './../bootstrap.php';

$object = new TestObject(['publicProperty' => 200]);
echo isset($object->publicProperty) ? 'YES' : 'NO', PHP_EOL;
echo isset($object->unknownProperty) ? 'YES' : 'NO', PHP_EOL;
?>
--EXPECT--
YES
NO
