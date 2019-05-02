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

use function foo\func;
use Immutable\ObjectContext;
use Immutable\Stub\TestObject;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ObjectContextTest extends TestCase
{
    public function testCouldNotSetStateDirectly()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Scope access violation');
        $instance = new class {
            public $publicProperty = 200;
        };
        ObjectContext::set($instance, ['publicProperty' => 100]);
    }

    public function testTryUpdateObjectContextFromOutside()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Scope access violation');
        $instance = new TestObject(['publicProperty' => 200]);
        ObjectContext::set($instance, ['publicProperty' => 100]);
    }

    public function testTryDestroyObjectContextFromOutside()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Scope access violation');
        $instance = new TestObject(['publicProperty' => 200]);
        ObjectContext::destroy($instance);
    }

    public function testTryUpdateObjectContextFromBoundClosure()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageRegExp('/Binding to the [\S]+ is not allowed/');
        $instance = new TestObject(['publicProperty' => 200]);
        $function = function () {
            ObjectContext::set($this, ['publicProperty' => 100]);
        };
        $function->call($instance);
    }

    public function testCallUnknownMethod()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageRegExp('/Unsupported method.*/');
        ObjectContext::someMethod();
    }
}
