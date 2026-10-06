<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Symfony;

use Kaveraa\SlugHistory\PastSlug;
use Kaveraa\SlugHistory\Store;
use Kaveraa\SlugHistory\Tests\Doctrine\Entity\Article;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The redirect seen from the outside: a real request, a real 404, a real
 * response.
 */
final class RedirectListenerTest extends BundleTestCase
{
    public function test_an_old_address_answers_a_permanent_redirection(): void
    {
        $container = $this->boot();
        $this->remember($container, 'mon-artcile', 'mon-article');

        $response = $this->get('/blog/mon-artcile');

        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode());
        self::assertSame('/blog/mon-article', $response->headers->get('Location'));
    }

    public function test_the_query_string_survives_the_redirection(): void
    {
        $container = $this->boot();
        $this->remember($container, 'mon-artcile', 'mon-article');

        $response = $this->get('/blog/mon-artcile?page=2&utm_source=lettre');

        self::assertSame('/blog/mon-article?page=2&utm_source=lettre', $response->headers->get('Location'));
    }

    public function test_an_ordinary_404_is_left_alone(): void
    {
        $this->boot();

        $response = $this->get('/jamais-vu');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertFalse($response->isRedirect());
    }

    public function test_a_post_is_not_redirected(): void
    {
        $container = $this->boot();
        $this->remember($container, 'mon-artcile', 'mon-article');

        $response = $this->get('/blog/mon-artcile', 'POST');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function test_the_status_can_keep_the_http_method(): void
    {
        $container = $this->boot(['status' => 308]);
        $this->remember($container, 'mon-artcile', 'mon-article');

        self::assertSame(
            Response::HTTP_PERMANENTLY_REDIRECT,
            $this->get('/blog/mon-artcile')->getStatusCode(),
        );
    }

    public function test_an_address_kept_in_another_scope_does_not_answer(): void
    {
        $container = $this->boot();
        $this->remember($container, 'contact', 'nous-ecrire', scope: 'fr');

        self::assertSame(Response::HTTP_NOT_FOUND, $this->get('/contact')->getStatusCode());
    }

    public function test_the_configured_scope_is_the_one_looked_up(): void
    {
        $container = $this->boot(['scope' => 'fr']);
        $this->remember($container, 'contact', 'nous-ecrire', scope: 'fr');

        self::assertSame('/nous-ecrire', $this->get('/contact')->headers->get('Location'));
    }

    public function test_without_the_automatic_redirection_nothing_happens(): void
    {
        $container = $this->boot(['auto_redirect' => false]);
        $this->remember($container, 'mon-artcile', 'mon-article');

        self::assertSame(Response::HTTP_NOT_FOUND, $this->get('/blog/mon-artcile')->getStatusCode());
    }

    private function remember(ContainerInterface $container, string $slug, string $current, string $scope = ''): void
    {
        $store = $container->get(Store::class);

        self::assertInstanceOf(Store::class, $store);

        $store->remember(new PastSlug(Article::class, 1, $slug, $current, $scope));
    }

    private function get(string $uri, string $method = 'GET'): Response
    {
        self::assertNotNull($this->kernel);

        return $this->kernel->handle(Request::create($uri, $method));
    }
}
