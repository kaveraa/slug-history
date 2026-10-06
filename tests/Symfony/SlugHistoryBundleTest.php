<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Symfony;

use Kaveraa\SlugHistory\Doctrine\DbalStore;
use Kaveraa\SlugHistory\Doctrine\SchemaInstaller;
use Kaveraa\SlugHistory\Doctrine\SlugHistoryListener;
use Kaveraa\SlugHistory\SlugHistory;
use Kaveraa\SlugHistory\Store;
use Kaveraa\SlugHistory\Symfony\RedirectListener;
use Kaveraa\SlugHistory\Tests\Doctrine\Entity\Article;
use Psr\Clock\ClockInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

final class SlugHistoryBundleTest extends BundleTestCase
{
    public function test_the_services_are_wired_with_their_defaults(): void
    {
        $container = $this->boot();

        self::assertInstanceOf(SlugHistory::class, $container->get(SlugHistory::class));
        self::assertInstanceOf(DbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(SchemaInstaller::class, $container->get(SchemaInstaller::class));
        self::assertInstanceOf(SlugHistoryListener::class, $container->get(SlugHistoryListener::class));
        self::assertInstanceOf(RedirectListener::class, $container->get(RedirectListener::class));

        self::assertSame('past_slugs', $container->getParameter('slug_history.table'));
        self::assertSame(301, $container->getParameter('slug_history.status'));
        self::assertSame('', $container->getParameter('slug_history.scope'));
        self::assertNull($container->getParameter('slug_history.keep_for_days'));
    }

    public function test_the_clock_of_the_application_is_not_overwritten(): void
    {
        $container = $this->boot();

        // Symfony already provides a PSR clock: the package plugs into it.
        self::assertInstanceOf(ClockInterface::class, $container->get(ClockInterface::class));
        self::assertNotInstanceOf(\Kaveraa\SlugHistory\SystemClock::class, $container->get(ClockInterface::class));
    }

    public function test_the_table_name_travels_all_the_way_to_the_store(): void
    {
        $container = $this->boot(['table' => 'old_urls'], install: false);

        $installer = $container->get(SchemaInstaller::class);
        self::assertInstanceOf(SchemaInstaller::class, $installer);
        self::assertSame('old_urls', $installer->name());
        $installer->install();

        $store = $container->get(Store::class);
        self::assertInstanceOf(Store::class, $store);
        $store->remember(new \Kaveraa\SlugHistory\PastSlug(Article::class, 1, 'ancien', 'nouveau'));

        self::assertSame(
            1,
            (int) $this->connection($container)->fetchOne('SELECT COUNT(*) FROM old_urls'),
        );
    }

    public function test_the_doctrine_listener_is_really_plugged_in(): void
    {
        $container = $this->boot();
        $entities = $this->entities($container);

        $article = new Article('mon-artcile');
        $entities->persist($article);
        $entities->flush();

        $article->slug = 'mon-article';
        $entities->flush();

        $history = $container->get(SlugHistory::class);
        self::assertInstanceOf(SlugHistory::class, $history);

        self::assertSame('mon-article', $history->find('mon-artcile')?->currentSlug);
    }

    public function test_the_redirection_can_be_switched_off(): void
    {
        $container = $this->boot(['auto_redirect' => false]);

        self::assertFalse($container->has(RedirectListener::class));
    }

    public function test_a_status_that_is_not_a_redirection_is_refused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->boot(['status' => 200]);
    }
}
