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

namespace Immutable\Functional;

use Immutable\Stub\SerializableTestObject;
use Immutable\Stub\TestObject;
use LogicException;
use PHPUnit\Framework\TestCase;

class ImmutableObjectTest extends TestCase
{
    /**
     * @var TestObject
     */
    private $testObject;

    protected function setUp()
    {
        $this->testObject = new TestObject([
            'privateProperty'   => 100,
            'protectedProperty' => 'bar',
            'publicProperty'    => [1, 2, 3]
        ]);
    }

    public function testDirectPublicPropertySetThrowsAnException()
    {
        $this->expectException(LogicException::class);
        $this->testObject->publicProperty = false;
    }

    public function testChangePrivatePropertyWithSetterThrowsAnException()
    {
        $this->expectException(LogicException::class);
        $this->testObject->setPrivateTo(false);
    }

    public function testChildSetProtectedPropertyThrowsAnException()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageRegExp('/You can not modify immutable property.*?protectedProperty/');
        $child = new class (['protectedProperty' => 100]) extends TestObject {
            public function setProtectedTo($newValue)
            {
                $this->protectedProperty = $newValue;
            }
        };
        $this->assertSame(100, $child->getProtected());
        $child->setProtectedTo(100500);
    }

    /**
     * @dataProvider provideTestObjectProperties
     *
     * @param string $propertyName Name of the property to change
     *
     * @throws \ReflectionException If there is no such property
     */
    public function testReflectionPropertySetThrowsAnException(string $propertyName)
    {
        $this->expectException(LogicException::class);
        $expectedMessage = 'You can not modify immutable property ' . TestObject::class . '->' . $propertyName;
        $this->expectExceptionMessage($expectedMessage);

        $reflection = new \ReflectionProperty(TestObject::class, $propertyName);
        $reflection->setAccessible(true);
        $reflection->setValue($this->testObject, 42);
    }

    /**
     * @dataProvider provideTestObjectProperties
     *
     * @param string $propertyName Name of the property to change
     */
    public function testBoundClosureSetPropertyThrowsAnException(string $propertyName)
    {
        $this->expectException(LogicException::class);
        $expectedMessage = 'You can not modify immutable property ' . TestObject::class . '->' . $propertyName;
        $this->expectExceptionMessage($expectedMessage);

        $closure = function (object $instance, string $propertyName, $newValue) {
            $instance->$propertyName = $newValue;
        };
        $boundClosure = $closure->bindTo($this->testObject, TestObject::class);
        $boundClosure($this->testObject, $propertyName, 42);
    }

    /**
     * @dataProvider provideTestObjectProperties
     *
     * @param string $propertyName Name of the property to change
     */
    public function testPropertyUnsetThrowsAnException(string $propertyName)
    {
        $this->expectException(LogicException::class);
        unset($this->testObject->$propertyName);
    }

    public function testDirectPublicPropertyIssetReturnsTrue()
    {
        $this->assertTrue(isset($this->testObject->publicProperty));
        $this->assertFalse(isset($this->testObject->unknownProperty));
    }

    public function testRawSerializationThrowsAnException()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Class ' . TestObject::class .' should implement Serializable interface');

        serialize($this->testObject);
    }

    public function testRawUnserializationThrowsAnException()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Class ' . TestObject::class .' should implement Serializable interface');

        $serialized = 'O:25:"Immutable\Stub\TestObject":0:{}';
        unserialize($serialized);
    }

    public function testCanSerializeUnserializeSerializableObject()
    {
        $object     = new SerializableTestObject(['publicProperty' => 100]);
        $serialized = serialize($object);
        $another    = unserialize($serialized);
        $this->assertSame(100, $another->publicProperty);
    }

    /**
     * I'm not sure why someone could need to clone immutable object :)
     */
    public function testCanCloneImmutableObject()
    {
        $another = clone $this->testObject;
        $this->assertSame($this->testObject->publicProperty, $another->publicProperty);
        $this->assertNotSame($another, $this->testObject);
    }

    public function testCanExportState()
    {
        $result   = preg_replace('/\s+/', '', print_r($this->testObject, true));
        $expected = 'Immutable\Stub\TestObjectObject([privateProperty]=>100[protectedProperty]=>bar[publicProperty]=>Array([0]=>1[1]=>2[2]=>3))';
        $this->assertSame($expected, $result);
    }

    /**
     * @internal
     */
    public static function provideTestObjectProperties(): array
    {
        $result     = [];
        $reflection = new \ReflectionClass(TestObject::class);
        foreach ($reflection->getProperties() as $property) {
            if ($property->getName() === '__objectId') {
                continue;
            }
            $result[] = [$property->getName()];
        }

        return $result;
    }
}
