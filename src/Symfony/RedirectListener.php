<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Symfony;

use Kaveraa\SlugHistory\SlugHistory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Rattrape les 404 : si l'adresse demandée est une ancienne adresse connue, la
 * réponse devient une redirection vers l'adresse actuelle.
 *
 * Catches 404s: if the requested address is a known past address, the response
 * becomes a redirection to the current one.
 *
 * Rien n'est demandé à la base tant qu'il n'y a pas de 404 : l'écouteur ne se
 * réveille que là, sur la requête principale, et seulement en GET ou HEAD.
 */
final class RedirectListener
{
    /** Les méthodes qu'on peut rejouer ailleurs sans rien casser. */
    private const SAFE = ['GET', 'HEAD'];

    public function __construct(
        private readonly SlugHistory $history,
        /** 301 pour un déménagement définitif, 308 pour garder la méthode HTTP. */
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
     * La nouvelle adresse, avec la chaîne de requête telle quelle : ?page=2 et
     * les étiquettes de campagne doivent survivre à la redirection.
     */
    private function target(Request $request, string $path): string
    {
        $query = $request->getQueryString();

        return $request->getBaseUrl() . $path . ($query === null || $query === '' ? '' : '?' . $query);
    }
}
