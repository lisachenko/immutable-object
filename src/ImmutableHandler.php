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
use ZEngine\ClassExtension\Hook\GetPropertyPointerHook;
use ZEngine\ClassExtension\Hook\InterfaceGetsImplementedHook;
use ZEngine\ClassExtension\Hook\UnsetPropertyHook;
use ZEngine\ClassExtension\Hook\WritePropertyHook;
use ZEngine\ClassExtension\ObjectCreateTrait;
use ZEngine\ClassExtension\ObjectGetPropertyPointerInterface;
use ZEngine\ClassExtension\ObjectUnsetPropertyInterface;
use ZEngine\ClassExtension\ObjectWritePropertyInterface;
use ZEngine\Core;
use ZEngine\Reflection\ReflectionClass;

/**
 * This ImmutableHandler controls the behaviour of
 */
final class ImmutableHandler implements
    ObjectWritePropertyInterface,
    ObjectGetPropertyPointerInterface,
    ObjectUnsetPropertyInterface
{
    public static function install(): void
    {
        $handler   = Closure::fromCallable([self::class, '__interfaceImplemented']);
        $interface = new ReflectionClass(ImmutableInterface::class);
        $interface->setInterfaceGetsImplementedHandler($handler);
    }

    public static function __interfaceImplemented(InterfaceGetsImplementedHook $hook): int
    {
        $objectCreateHandler       = (new ReflectionMethod(ObjectCreateTrait::class, '__init'))->getClosure();
        $objectFieldWriteHandler   = (new ReflectionMethod(self::class, '__fieldWrite'))->getClosure();
        $objectFieldPointerHandler = (new ReflectionMethod(self::class, '__fieldPointer'))->getClosure();
        $objectFieldUnsetHandler   = (new ReflectionMethod(self::class, '__fieldUnset'))->getClosure();

        $implementor = $hook->getClass();
        $implementor->setCreateObjectHandler($objectCreateHandler);
        $implementor->setWritePropertyHandler($objectFieldWriteHandler);
        $implementor->setGetPropertyPointerHandler($objectFieldPointerHandler);
        $implementor->setUnsetPropertyHandler($objectFieldUnsetHandler);

        return Core::SUCCESS;
    }

    /**
     * @inheritDoc
     */
    public static function __fieldWrite(WritePropertyHook $hook)
    {
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
}
