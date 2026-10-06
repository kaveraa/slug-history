<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Symfony;

use Kaveraa\SlugHistory\SlugHistory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Catches 404s: if the requested address is a known past address, the response
 * becomes a redirection to the current one.
 *
 * Nothing is asked of the database until there is a 404: the listener only
 * wakes up there, on the main request, and only for GET or HEAD.
 */
final class RedirectListener
{
    /** The methods that can be replayed elsewhere without breaking anything. */
    private const SAFE = ['GET', 'HEAD'];

    public function __construct(
        private readonly SlugHistory $history,
        /** 301 for a permanent move, 308 to keep the HTTP method. */
        private readonly int $status = 301,
        private readonly string $scope = '',
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getThrowable() instanceof NotFoundHttpException) {
            return;
        }

        $request = $event->getRequest();

        if (!in_array($request->getMethod(), self::SAFE, true)) {
            return;
        }

        $path = $this->history->newPathFor($request->getPathInfo(), $this->scope);

        if ($path === null) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->target($request, $path), $this->status));
    }

    /**
     * The new address, with the query string as it is: ?page=2 and the
     * campaign tags must survive the redirect.
     */
    private function target(Request $request, string $path): string
    {
        $query = $request->getQueryString();

        return $request->getBaseUrl() . $path . ($query === null || $query === '' ? '' : '?' . $query);
    }
}
