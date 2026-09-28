<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Symfony;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Branche l'horloge PSR du paquet, sans jamais écraser celle de l'application :
 * celle de Symfony implémente déjà l'interface, on ne la remplace pas.
 *
 * Wires the PSR clock of the package, without ever overwriting the one the
 * application already has.
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
