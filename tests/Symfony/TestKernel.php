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
 * Minimal Symfony application: FrameworkBundle + DoctrineBundle + SlugHistoryBundle.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** @var list<string> services made public for the tests */
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
     * @param array<string, mixed> $slugHistory the slug_history configuration
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
     * Everything Symfony writes stays in a temporary folder: the package
     * repository must never receive a generated file.
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
        // The package services are private: the tests need to read them.
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
        // No route: every requested path ends in a 404, which is what the
        // redirect listener waits for to wake up.
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

        // Without a logger, Symfony writes the expected 404s to the error output.
        $container->services()->set('logger', NullLogger::class);
    }
}
