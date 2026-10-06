<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Laravel\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Kaveraa\SlugHistory\SlugHistory;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catches 404s: if the requested path is a past address, redirects to the
 * current one.
 *
 * The history is read only when the response is a 404. So a page that exists
 * causes no extra query: this is what makes it safe to keep this middleware
 * in production.
 */
final class RedirectToCurrentSlug
{
    /** The only methods a search engine or an old link uses. */
    private const METHODS = ['GET', 'HEAD'];

    public function __construct(
        private readonly SlugHistory $history,
        private readonly int $status = 301,
        /** A fixed scope, or a function that reads it from the request. */
        private readonly Closure|string $scope = '',
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!in_array($request->getMethod(), self::METHODS, true)) {
            return $response;
        }

        if ($response->getStatusCode() !== 404) {
            return $response;
        }

        $path = $this->history->newPathFor($request->getPathInfo(), $this->scopeFor($request));

        if ($path === null) {
            return $response;
        }

        return new RedirectResponse($path . $this->queryString($request), $this->status);
    }

    /**
     * The query string follows the redirect: ?page=2, the campaign parameters,
     * everything the visitor had in the link.
     */
    private function queryString(Request $request): string
    {
        $query = $request->getQueryString();

        return $query === null || $query === '' ? '' : '?' . $query;
    }

    private function scopeFor(Request $request): string
    {
        return $this->scope instanceof Closure ? (string) ($this->scope)($request) : $this->scope;
    }
}
