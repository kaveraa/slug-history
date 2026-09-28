<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Symfony;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Kaveraa\SlugHistory\Symfony\SlugHistoryBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Application Symfony minimale : FrameworkBundle + DoctrineBundle + SlugHistoryBundle.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** @var list<string> services rendus publics pour les tests */
    public const EXPOSED = [
        'Kaveraa\SlugHistory\SlugHistory',
        'Kaveraa\SlugHistory\Store',
        'Kaveraa\SlugHistory\Doctrine\DbalStore',
        'Kaveraa\SlugHistory\Doctrine\SchemaInstaller',
        'Kaveraa\SlugHistory\Doctrine\SlugSettings',
        'Kaveraa\SlugHistory\Doctrine\SlugHistoryListener',
        'Kaveraa\SlugHistory\Symfony\RedirectListener',
        'Kaveraa\SlugHistory\Symfony\Command\InstallCommand',
        'Kaveraa\SlugHistory\Symfony\Command\PurgeCommand',
        'Kaveraa\SlugHistory\Symfony\Command\HistoryCommand',
        'Psr\Clock\ClockInterface',
        'doctrine.orm.entity_manager',
        'doctrine.dbal.default_connection',
    ];

    /**
     * @param array<string, mixed> $slugHistory configuration slug_history
     */
    public function __construct(private readonly array $slugHistory = [])
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new SlugHistoryBundle();
    }

    /**
     * Tout ce que Symfony écrit reste dans un dossier temporaire : le dépôt du
     * paquet ne doit jamais recevoir de fichier engendré.
     */
    public function getProjectDir(): string
    {
        $dir = sys_get_temp_dir() . '/slug-history-tests/' . md5(serialize($this->slugHistory));

        if (!is_dir($dir . '/config')) {
            mkdir($dir . '/config', 0o777, true);
        }

        return $dir;
    }

    public function getConfigDir(): string
    {
        return $this->getProjectDir() . '/config';
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/log';
    }

    protected function build(ContainerBuilder $container): void
    {
        // Les services du paquet sont privés : les tests ont besoin de les lire.
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach (TestKernel::EXPOSED as $id) {
                    if ($container->hasAlias($id)) {
                        $container->getAlias($id)->setPublic(true);

                        continue;
                    }

                    if ($container->hasDefinition($id)) {
                        $container->getDefinition($id)->setPublic(true);
                    }
                }
            }
        }, PassConfig::TYPE_BEFORE_REMOVING);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // Aucune route : tout chemin demandé finit en 404, ce que l'écouteur
        // de redirection attend pour se réveiller.
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'router' => ['utf8' => true],
            'http_method_override' => false,
        ]);

        $container->extension('doctrine', [
            'dbal' => ['driver' => 'pdo_sqlite', 'memory' => true, 'logging' => false, 'profiling' => false],
            'orm' => [
                'mappings' => [
                    'Test' => [
                        'type' => 'attribute',
                        'dir' => \dirname(__DIR__) . '/Doctrine/Entity',
                        'prefix' => 'Kaveraa\SlugHistory\Tests\Doctrine\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        $container->extension('slug_history', $this->slugHistory);

        // Sans journal, Symfony écrit les 404 attendues sur la sortie d'erreur.
        $container->services()->set('logger', NullLogger::class);
    }
}
