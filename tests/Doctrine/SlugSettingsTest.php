<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Doctrine;

use Kaveraa\SlugHistory\Doctrine\SlugSettings;
use Kaveraa\SlugHistory\Tests\Doctrine\Entity\Article;
use Kaveraa\SlugHistory\Tests\Doctrine\Entity\Comment;
use Kaveraa\SlugHistory\Tests\Doctrine\Entity\Page;
use Kaveraa\SlugHistory\Tests\Doctrine\Entity\Recipe;

final class SlugSettingsTest extends DoctrineTestCase
{
    private SlugSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = new SlugSettings();
    }

    public function test_the_ordinary_case_uses_the_slug_property(): void
    {
        self::assertSame('slug', $this->settings->slugProperty($this->em->getClassMetadata(Article::class)));
    }

    public function test_the_property_can_be_named_otherwise(): void
    {
        self::assertSame('permalink', $this->settings->slugProperty($this->em->getClassMetadata(Page::class)));
    }

    public function test_a_class_without_the_attribute_is_ignored(): void
    {
        self::assertNull($this->settings->of(Comment::class));
        self::assertNull($this->settings->slugProperty($this->em->getClassMetadata(Comment::class)));
    }

    public function test_the_scope_is_read_on_the_entity(): void
    {
        $meta = $this->em->getClassMetadata(Recipe::class);

        self::assertSame('en', $this->settings->scopeOf($meta, new Recipe('pancakes', 'en')));
        self::assertSame('', $this->settings->scopeOf($this->em->getClassMetadata(Article::class), new Article()));
    }

    public function test_the_identifier_is_the_one_doctrine_knows(): void
    {
        $article = new Article('mon-artcile');
        $this->save($article);

        self::assertSame($article->id, $this->settings->idOf($this->em->getClassMetadata(Article::class), $article));
        self::assertNull($this->settings->idOf($this->em->getClassMetadata(Article::class), new Article()));
    }

    public function test_the_real_class_is_found_behind_a_doctrine_proxy(): void
    {
        $article = new Article('mon-artcile');
        $this->save($article);
        $this->em->clear();

        $proxy = $this->em->getReference(Article::class, (int) $article->id);

        self::assertNotNull($proxy);

        $meta = $this->em->getClassMetadata($proxy::class);

        self::assertSame(Article::class, $meta->getName());
        self::assertSame('slug', $this->settings->slugProperty($meta));
    }

    /**
     * Le nom de classe d'un proxy engendré ne porte aucun attribut : seules les
     * métadonnées savent quelle classe se cache derrière.
     */
    public function test_a_generated_proxy_class_name_carries_no_attribute(): void
    {
        $proxyClass = 'Proxies\\__CG__\\' . Article::class;

        self::assertNull($this->settings->of($proxyClass));
        self::assertSame(Article::class, $this->em->getClassMetadata($proxyClass)->getName());
    }
}
