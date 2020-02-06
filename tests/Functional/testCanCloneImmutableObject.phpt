--TEST--
Writing property of immutable object throws an error
--FILE--
<?php
declare(strict_types=1);

use Immutable\Stub\TestObject;

include __DIR__ . './../bootstrap.php';

$object  = new TestObject(['publicProperty' => 200]);
$another = clone $object;
echo ($object->publicProperty === $another->publicProperty) ? 'PROP OK' : 'PROP NO', PHP_EOL;
echo $another !== $object ? 'CLONE OK' : 'CLONE NO', PHP_EOL;
?>
--EXPECT--
PROP OK
CLONE OK
