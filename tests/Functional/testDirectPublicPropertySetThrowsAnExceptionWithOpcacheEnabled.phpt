--TEST--
Writing property of immutable object throws an error when OPcache is enabled
--INI--
ffi.enable=1
opcache.jit=off
opcache.enable_cli=1
--SKIPIF--
<?php
if (!extension_loaded('Zend OPcache')) {
    echo 'skip Zend OPcache is not available in this build';
}
--FILE--
<?php
declare(strict_types=1);

use Immutable\Stub\TestObject;

include __DIR__ . './../bootstrap.php';

echo 'OPCACHE ', (ini_get('opcache.enable_cli') ? 'ON' : 'OFF'), PHP_EOL;

$object = new TestObject(['publicProperty' => 200]);
$object->publicProperty = 300;
?>
--EXPECTREGEX--
OPCACHE ON[\s\S]*Immutable object could be modified only in constructor or static methods
