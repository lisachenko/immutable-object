--TEST--
Installation reports loudly when the installed handlers would not be reached
--INI--
ffi.enable=1
opcache.jit=off
opcache.enable_cli=0
--FILE--
<?php
declare(strict_types=1);

use Immutable\EnforcementProbe;
use Immutable\ImmutableHandler;
use ZEngine\Core;

ini_set('display_errors', 'on');

include __DIR__ . './../../vendor/autoload.php';

Core::init();

// Link a class implementing ImmutableInterface *before* install() runs: the "interface gets
// implemented" handler never sees it, so nothing can be hooked on it afterwards. This is what
// OPcache preloading does to every preloaded class, and what OPcache used to do to every class.
class_exists(EnforcementProbe::class);

try {
    ImmutableHandler::install();
    echo 'NO EXCEPTION', PHP_EOL;
} catch (RuntimeException $exception) {
    echo 'RuntimeException', PHP_EOL;
    echo str_contains($exception->getMessage(), 'Immutability can not be enforced') ? 'REASON OK' : 'REASON NO', PHP_EOL;
    echo str_contains($exception->getMessage(), 'opcache.enable_cli=0') ? 'WORKAROUND OK' : 'WORKAROUND NO', PHP_EOL;
}
?>
--EXPECT--
RuntimeException
REASON OK
WORKAROUND OK
