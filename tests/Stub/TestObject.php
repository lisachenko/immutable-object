<?php
/**
 * Immutable object library
 *
 * @copyright Copyright 2019 Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);
/**
 * Immutable object library
 *
 * @copyright Copyright 2019 Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Immutable\Stub;

use Immutable\ImmutableTrait;

class TestObject
{
    use ImmutableTrait;

    private $privateProperty;
    protected $protectedProperty;
    public $publicProperty;

    public function setPrivateTo($newValue)
    {
        $this->privateProperty = $newValue;
    }
    public function getProtected()
    {
        return $this->protectedProperty;
    }
}
