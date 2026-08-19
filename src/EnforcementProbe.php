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

/**
 * Throwaway class used by ImmutableHandler::install() to verify that the property handlers
 * it installs are really reached by the engine.
 *
 * It must not be referenced anywhere else: the self-check is only meaningful as long as this
 * class is linked *after* the "interface gets implemented" handler has been installed, which
 * is exactly the condition every user class has to satisfy as well.
 *
 * @internal
 */
final class EnforcementProbe implements ImmutableInterface
{
    public int $probe = 0;
}
