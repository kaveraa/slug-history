<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Symfony;

use Kaveraa\SlugHistory\Doctrine\DbalStore;
use Kaveraa\SlugHistory\Doctrine\SchemaInstaller;
use Kaveraa\SlugHistory\Doctrine\SlugHistoryListener;
use Kaveraa\SlugHistory\Doctrine\SlugSettings;
use Kaveraa\SlugHistory\SlugHistory;
use Kaveraa\SlugHistory\Store;
use Kaveraa\SlugHistory\Symfony\Command\HistoryCommand;
use Kaveraa\SlugHistory\Symfony\Command\InstallCommand;
use Kaveraa\SlugHistory\Symfony\Command\PurgeCommand;
use Kaveraa\SlugHistory\SystemClock;
use Psr\Clock\ClockInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Bundle Symfony : la table des anciennes adresses, l'écoute des renommages
 * Doctrine, la redirection automatique et les trois commandes console.
 *
 * Symfony bundle: the table of past addresses, the Doctrine rename listener,
 * the automatic redirection and the three console commands.
 *
 * Activation dans config/bundles.php :
 *
 *     Kaveraa\SlugHistory\Symfony\SlugHistoryBundle::class => ['all' => true],
 *
 * Configuration (config/packages/slug_history.yaml) :
 *
 *     slug_history:
 *         table: past_slugs
 *         status: 301          # 308 pour garder la méthode HTTP
 *         auto_redirect: true  # branche l'écouteur de redirection tout seul
 *         keep_for_days: ~     # null = pour toujours
 *         scope: ''
 */
final class SlugHistoryBundle extends AbstractBundle
{
    protected string $extensionAlias = 'slug_history';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new ClockAliasPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('table')
                    ->info('La table qui garde les anciennes adresses.')
                    ->cannotBeEmpty()
                    ->defaultValue('past_slugs')
                ->end()
                ->integerNode('status')
                    ->info('Le code de la redirection. 301 : déménagement définitif. 308 : idem, en gardant la méthode HTTP.')
                    ->defaultValue(301)
                    ->validate()
                        ->ifNotInArray([301, 302, 307, 308])
                        ->thenInvalid('Le code de redirection doit être 301, 302, 307 ou 308, pas %s.')
                    ->end()
                ->end()
                ->booleanNode('auto_redirect')
                    ->info('Rattraper les 404 tout seul. Mettez false pour rediriger vous-même.')
                    ->defaultTrue()
                ->end()
                ->integerNode('keep_for_days')
                    ->info('Durée de conservation en jours, pour slugs:purge. Null : pour toujours.')
                    ->min(1)
                    ->defaultNull()
                ->end()
                ->scalarNode('scope')
                    ->info('La portée par défaut : la langue, la rubrique parente, ce qui rend l\'adresse unique.')
                    ->defaultValue('')
                ->end()
            ->end();
    }

    /**
     * @param array{
     *     table: string,
     *     status: int,
     *     auto_redirect: bool,
     *     keep_for_days: int|null,
     *     scope: string,
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('slug_history.table', $config['table'])
            ->set('slug_history.status', $config['status'])
            ->set('slug_history.scope', $config['scope'])
            ->set('slug_history.keep_for_days', $config['keep_for_days']);

        $services = $container->services();

        // L'alias Psr\Clock\ClockInterface n'est posé que si l'application n'en a
        // pas déjà un (voir ClockAliasPass).
        $services->set('slug_history.clock', SystemClock::class);

        if (!self::hasDoctrine($builder)) {
            // Sans DoctrineBundle il n'y a pas de connexion : rien à brancher.
            return;
        }

        $services->set(DbalStore::class)
            ->args([service('doctrine.dbal.default_connection'), $config['table']]);

        $services->alias(Store::class, DbalStore::class);

        $services->set(SchemaInstaller::class)
            ->args([service('doctrine.dbal.default_connection'), $config['table']]);

        $services->set(SlugSettings::class);

        $services->set(SlugHistory::class)
            ->args([service(Store::class), service(ClockInterface::class)]);

        $services->set(SlugHistoryListener::class)
            ->args([service(SlugHistory::class), service(SlugSettings::class)])
            ->tag('doctrine.event_listener', ['event' => 'postPersist'])
            ->tag('doctrine.event_listener', ['event' => 'postUpdate'])
            ->tag('doctrine.event_listener', ['event' => 'preRemove'])
            ->tag('doctrine.event_listener', ['event' => 'postRemove']);

        $services->set(InstallCommand::class)
            ->args([service(SchemaInstaller::class)])
            ->tag('console.command');

        $services->set(PurgeCommand::class)
            ->args([service(SlugHistory::class), $config['keep_for_days']])
            ->tag('console.command');

        $services->set(HistoryCommand::class)
            ->args([service(SlugHistory::class)])
            ->tag('console.command');

        if (!$config['auto_redirect']) {
            return;
        }

        $services->set(RedirectListener::class)
            ->args([service(SlugHistory::class), $config['status'], $config['scope']])
            ->tag('kernel.event_listener', ['event' => 'kernel.exception']);
    }

    /**
     * DoctrineBundle est-il installé ? Pendant le chargement des extensions, le
     * conteneur reçu ne connaît pas les autres extensions : on regarde la liste
     * des bundles du noyau.
     */
    private static function hasDoctrine(ContainerBuilder $builder): bool
    {
        if (!$builder->hasParameter('kernel.bundles')) {
            return false;
        }

        /** @var array<string, string> $bundles */
        $bundles = (array) $builder->getParameter('kernel.bundles');

        return isset($bundles['DoctrineBundle']);
    }
}
