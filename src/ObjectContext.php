<?php
declare(strict_types=1);
/**
 * Immutable object library
 *
 * @copyright Copyright 2019 Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Immutable;

use LogicException;
use function debug_backtrace, in_array, spl_object_id;

/**
 * Class ObjectContext
 *
 * @method static set(object $instance, array $value): void Initializes instance state
 * @method static get(object $instance): array Returns instance state
 * @method static destroy(object $instance): void Destroys instance in memory
 * @method static copy(int $sourceObjectId, int $destinationObjectId): void Copies context from one objectId to another
 */
final class ObjectContext
{
    /**
     * Static magic-method handler
     *
     * @param string $name      Method name to call
     * @param array  $arguments Method arguments
     *
     * @return array|void
     */
    final public static function __callStatic(string $name, array $arguments)
    {
        // Static variable in the function prevents changing its value from the outside
        static $immutableObjects = [];

        switch ($name) {
            case 'set':
                [$instance, $value] = $arguments;
                self::setObjectState($instance, $value, $immutableObjects);
                break;

            case 'get':
                [$instance] = $arguments;

                return self::getObjectState($instance, $immutableObjects);

            case 'destroy':
                [$instance] = $arguments;
                self::destroyObjectState($instance, $immutableObjects);
                break;

            case 'copy':
                [$sourceObjectId, $destinationObjectId] = $arguments;
                self::copyObjectState($sourceObjectId, $destinationObjectId, $immutableObjects);
                break;

            default:
                throw new \RuntimeException("Unsupported method ($name)");
        }
    }

    /**
     * Returns an object state as key=>value associative array
     *
     * @param object $instance         Instance of immutable object
     * @param array  $immutableObjects Storage of all immutable object states, by reference
     *
     * @return array Previously stored object state
     * @throws LogicException If there is no object state for given instance
     */
    final private static function getObjectState(object $instance, array &$immutableObjects): array
    {
        self::guardInstanceScope($instance);
        $objectId = spl_object_id($instance);
        if (!isset($immutableObjects[$objectId])) {
            throw new LogicException('Immutable object context is not available');
        }

        return $immutableObjects[$objectId] ?? [];
    }

    /**
     * Stores an object state in the storage
     *
     * @param object $instance         Instance of immutable object
     * @param array  $value            Object state to store
     * @param array  $immutableObjects Storage of all immutable object states, by reference
     *
     * @throws LogicException If there is an object state already for given instance
     */
    private static function setObjectState(object $instance, array $value, array &$immutableObjects): void
    {
        self::guardInstanceScope($instance);
        $objectId = spl_object_id($instance);
        if (isset($immutableObjects[$objectId])) {
            throw new LogicException('Immutable values can be assigned only once');
        }
        $immutableObjects[$objectId] = $value;
    }

    /**
     * Performs cleaning of object state in the storage
     *
     * @param object $instance         Instance of immutable object
     * @param array  $immutableObjects Storage of all immutable object states, by reference
     */
    private static function destroyObjectState(object $instance, array &$immutableObjects): void
    {
        self::guardInstanceScope($instance);
        $objectId = spl_object_id($instance);
        if (isset($immutableObjects[$objectId])) {
            unset($immutableObjects[$objectId]);
        }
    }

    /**
     * Performs copying of state by object identifiers
     *
     * @param int   $sourceObjectId      Source object ID
     * @param int   $destinationObjectId Destination object ID
     * @param array $immutableObjects    Storage of all immutable object states, by reference
     */
    private static function copyObjectState(
        int $sourceObjectId,
        int $destinationObjectId,
        array &$immutableObjects
    ): void {
        if (!isset($immutableObjects[$sourceObjectId])) {
            throw new LogicException('Immutable object context is not available');
        }
        if (isset($immutableObjects[$destinationObjectId])) {
            throw new LogicException('Immutable values can be assigned only once');
        }
        $immutableObjects[$destinationObjectId] = $immutableObjects[$sourceObjectId];
    }

    /**
     * Protects our code from calling directly
     *
     * @param object $instance Instance of immutable object
     */
    final private static function guardInstanceScope(object $instance): void
    {
        static $knownThrowMethods = ['__set', '__unset', '__sleep', '__wakeup'];

        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        if (!isset($trace[3]['object']) || $trace[3]['object'] !== $instance) {
            throw new LogicException('Scope access violation');
        }
        $isClosure   = strpos($trace[3]['function'], '{closure}') !== false;
        $isDebugInfo = $trace[3]['function'] === '__debugInfo';
        $isInternal  = isset($trace[4]['class']) && ($trace[3]['class'] === $trace[4]['class']);
        $isInternal  = $isInternal && !(in_array($trace[4]['function'], $knownThrowMethods, true));
        if ($isClosure) {
            throw new LogicException('Binding to the ' . get_class($instance) . ' is not allowed');
        }
        if ($isDebugInfo && $isInternal) {
            throw new LogicException('The class ' .get_class($instance) . ' should not be debugged');
        }
    }
}
