--TEST--
Updating protected property of immutable object in child class throws an error
--FILE--
<?php
declare(strict_types=1);

use Immutable\Stub\TestObject;

include __DIR__ . './../bootstrap.php';

$child = new class (['protectedProperty' => 100]) extends TestObject {
    public function setProtectedTo($newValue)
    {
        $this->protectedProperty = $newValue;
    }
};
// no errors during read operation
$value = $child->getProtected();
$child->setProtectedTo(100500);
?>
--EXPECTREGEX--
Immutable object could be modified only in constructor or static methods
