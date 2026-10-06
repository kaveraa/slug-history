<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Symfony;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Wires the PSR clock of the package, without ever overwriting the one the
 * application already has: Symfony's own clock implements the interface, so
 * it is not replaced.
 */
final class ClockAliasPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ClockInterface::class) && $container->has('slug_history.clock')) {
            $container->setAlias(ClockInterface::class, 'slug_history.clock');
        }
    }
}
