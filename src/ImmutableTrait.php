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
use Throwable;
use function array_keys, get_object_vars, spl_object_id, serialize, unserialize;

trait ImmutableTrait
{
    /**
     * Stores a unique identifier of this object in PHP, used only for cloning
     *
     * @var int
     */
    private $__objectId;

    /**
     * Constructs an instance of immutable object
     *
     * @param array $properties Initial value for properties
     */
    final public function __construct(array $properties)
    {
        $this->applyState($properties);
    }

    /**
     * Prevents an update of immutable property
     *
     * @param mixed $value New value for property
     *
     * @throws LogicException
     */
    final public function __set(string $name, $value): void
    {
        throw new LogicException('You can not modify immutable property ' . static::class . '->' . $name);
    }

    /**
     * Returns property value
     *
     * @return mixed
     */
    final public function __get(string $name)
    {
        return ObjectContext::get($this)[$name] ?? null;
    }

    /**
     * Checks if a property is set
     */
    final public function __isset(string $name): bool
    {
        return isset(ObjectContext::get($this)[$name]);
    }

    /**
     * Prevents unset of immutable property
     *
     * @throws LogicException
     */
    final public function __unset(string $name): void
    {
        throw new LogicException('You can not unset immutable property ' . static::class . '->' . $name);
    }

    /**
     * Returns debug representation of object
     */
    final public function __debugInfo(): array
    {
        $result = [];
        try {
            $result = ObjectContext::get($this);
        } catch (Throwable $e) {
            die ($e->getMessage());
        } finally {
            return $result;
        }
    }

    /**
     * Destroys immutable object
     */
    final public function __destruct()
    {
        ObjectContext::destroy($this);
    }

    /**
     * Clone handler for immutable object performs context copying during clone procedure
     */
    final public function __clone()
    {
        $newObjectId = spl_object_id($this);
        ObjectContext::copy($this->__objectId, $newObjectId);
        $this->__objectId = $newObjectId;
    }

    /**
     * Serialization handler for immutable objects
     */
    final public function serialize(): string
    {
        return serialize(ObjectContext::get($this));
    }

    /**
     * Unserialization handler
     *
     * @param string $serialized
     */
    final public function unserialize($serialized)
    {
        $state = unserialize($serialized);
        $this->applyState($state);
    }

    /**
     * Protects class from default serialization handler
     */
    final public function __sleep()
    {
        throw new LogicException('Class ' . static::class . ' should implement Serializable interface');
    }

    /**
     * Protects class from default unserialization handler
     */
    final public function __wakeup()
    {
        throw new LogicException('Class ' . static::class . ' should implement Serializable interface');
    }

    /**
     * Applies given state to the object context, can be performed only once for uninitialized object
     *
     * @param array $properties
     */
    final private function applyState(array $properties)
    {
        $state = [];
        foreach (array_keys(get_object_vars($this)) as $propertyName) {
            if ($propertyName === '__objectId') {
                continue;
            }
            if (isset($properties[$propertyName])) {
                $this->$propertyName = $properties[$propertyName];
            }
            $state[$propertyName] = &$this->$propertyName;
            unset($this->$propertyName);
        }
        $this->__objectId = spl_object_id($this);
        ObjectContext::set($this, $state);
    }
}
