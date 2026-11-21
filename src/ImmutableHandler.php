<?php
/**
 * Immutable object library
 *
 * @copyright Copyright 2020 Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Immutable;

use Closure;
use ReflectionMethod;
use RuntimeException;
use ZEngine\ClassExtension\Hook\CreateObjectHook;
use ZEngine\ClassExtension\Hook\GetPropertyPointerHook;
use ZEngine\ClassExtension\Hook\InterfaceGetsImplementedHook;
use ZEngine\ClassExtension\Hook\UnsetPropertyHook;
use ZEngine\ClassExtension\Hook\WritePropertyHook;
use ZEngine\ClassExtension\ObjectCreateInterface;
use ZEngine\ClassExtension\ObjectGetPropertyPointerInterface;
use ZEngine\ClassExtension\ObjectUnsetPropertyInterface;
use ZEngine\ClassExtension\ObjectWritePropertyInterface;
use ZEngine\Core;
use ZEngine\Reflection\ReflectionClass;

/**
 * This ImmutableHandler controls the behaviour of
 */
final class ImmutableHandler implements
    ObjectCreateInterface,
    ObjectWritePropertyInterface,
    ObjectGetPropertyPointerInterface,
    ObjectUnsetPropertyInterface
{
    /**
     * Addresses of the zend_class_entry structures that already carry the property handlers
     *
     * The object handlers table z-engine installs into is keyed by the address of the class
     * entry, so the bookkeeping here has to use the very same key.
     *
     * @var array<int, true>
     */
    private static array $preparedClassEntries = [];

    /**
     * Set while install() runs its enforcement self-check
     */
    private static bool $isProbingEnforcement = false;

    /**
     * Set by __fieldWrite() when the self-check write actually reached the handler
     */
    private static bool $wasProbeWriteIntercepted = false;

    public static function install(): void
    {
        $handler   = Closure::fromCallable([self::class, '__interfaceImplemented']);
        $interface = new ReflectionClass(ImmutableInterface::class);
        $interface->setInterfaceGetsImplementedHandler($handler);

        self::verifyEnforcementIsActive();
    }

    public static function __interfaceImplemented(InterfaceGetsImplementedHook $hook): int
    {
        $objectCreateHandler = Closure::fromCallable([self::class, '__init']);

        // Only the create_object handler is installed here, because it lives in the class entry
        // itself and therefore survives the OPcache round-trip through shared memory. The
        // property handlers live in a separate object handlers table that z-engine keys by the
        // address of the class entry, and with OPcache enabled the class entry seen here is not
        // the one the engine uses at runtime - so they are installed lazily from __init(), which
        // does receive the runtime class entry. See lisachenko/z-engine#238.
        $hook->getClass()->setCreateObjectHandler($objectCreateHandler);

        return Core::SUCCESS;
    }

    /**
     * Performs low-level initialization of object during new instances creation
     *
     * @inheritDoc
     */
    public static function __init(CreateObjectHook $hook): object
    {
        // Must happen before proceed(): the object being created picks up the object handlers
        // table for its class entry, and that table is what the property handlers are written to.
        self::prepareClassEntry($hook->getClassType());

        return $hook->proceed();
    }

    /**
     * @inheritDoc
     */
    public static function __fieldWrite(WritePropertyHook $hook)
    {
        if (self::$isProbingEnforcement) {
            self::$wasProbeWriteIntercepted = true;

            return $hook->getValue();
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS|DEBUG_BACKTRACE_PROVIDE_OBJECT, 3);
        $frame = $trace[2] ?? [];
        if (!isset($frame['class'])) {
            throw new \LogicException('Immutable object could be modified only in constructor or static methods');
        }
        try {
            $refMethod = new ReflectionMethod($frame['class'], $frame['function']);
        } catch(\ReflectionException $e) {
            $refMethod = null;
        }
        if (!$refMethod || !($refMethod->isConstructor() || $refMethod->isStatic())) {
            throw new \LogicException('Immutable object could be modified only in constructor or static methods');
        }

        return $hook->getValue();
    }

    /**
     * @inheritDoc
     */
    public static function __fieldPointer(GetPropertyPointerHook $hook)
    {
        throw new \LogicException("Indirect modification of immutable field is restricted");
    }

    /**
     * @inheritDoc
     */
    public static function __fieldUnset(UnsetPropertyHook $hook): void
    {
        throw new \LogicException("Unset of immutable field is restricted");
    }

    /**
     * Installs the property handlers for the runtime class entry of an implementor
     *
     * @param object $classEntry Raw zend_class_entry pointer received by the create_object handler
     */
    private static function prepareClassEntry(object $classEntry): void
    {
        $classEntryAddress = Core::addressOf($classEntry);
        if (isset(self::$preparedClassEntries[$classEntryAddress])) {
            return;
        }
        // Marked as prepared up-front, so that any object creation happening while the handlers
        // are being installed can not re-enter this method for the same class entry.
        self::$preparedClassEntries[$classEntryAddress] = true;

        $objectFieldWriteHandler   = Closure::fromCallable([self::class, '__fieldWrite']);
        $objectFieldPointerHandler = Closure::fromCallable([self::class, '__fieldPointer']);
        $objectFieldUnsetHandler   = Closure::fromCallable([self::class, '__fieldUnset']);

        // Deliberately not guarded by a try/catch: swallowing a failure here would restore the
        // silent no-op this whole mechanism exists to prevent. install() has already proven on a
        // throwaway class that this code path works in the current environment.
        $implementor = ReflectionClass::fromCData($classEntry);
        $implementor->setWritePropertyHandler($objectFieldWriteHandler);
        $implementor->setGetPropertyPointerHandler($objectFieldPointerHandler);
        $implementor->setUnsetPropertyHandler($objectFieldUnsetHandler);
    }

    /**
     * Proves on a throwaway class that a property write is really intercepted
     *
     * This is an empirical check on purpose: an environment can defeat the handlers in more ways
     * than an ini setting can describe, and the only answer that matters is whether a write to an
     * object of a class that implements ImmutableInterface reaches the handler or not.
     *
     * The probe records a flag instead of throwing, because the handler runs inside an FFI
     * callback and an exception crossing that boundary is an uncatchable fatal error.
     */
    private static function verifyEnforcementIsActive(): void
    {
        self::$wasProbeWriteIntercepted = false;
        self::$isProbingEnforcement     = true;
        try {
            // The probe class is linked here, i.e. after the handler above has been installed
            $probe        = new EnforcementProbe();
            $probe->probe = 1;
        } finally {
            self::$isProbingEnforcement = false;
        }

        if (self::$wasProbeWriteIntercepted) {
            return;
        }

        throw new RuntimeException(
            'Immutability can not be enforced in this environment: a write to a property of a class implementing '
            . ImmutableInterface::class . ' is not intercepted, so writes outside of a constructor would silently '
            . 'succeed instead of raising an error. Check that ImmutableHandler::install() runs before any class '
            . 'implementing ' . ImmutableInterface::class . ' is linked - OPcache preloading links classes ahead of '
            . 'it and those are never seen by the handler. If that is already the case, the engine hooks are not '
            . 'taking effect at all (see https://github.com/lisachenko/z-engine/issues/238): disable OPcache for this '
            . 'process with opcache.enable=0, or opcache.enable_cli=0 on CLI.',
        );
    }
}
