<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Laravel\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Kaveraa\SlugHistory\SlugHistory;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rattrape les 404 : si le chemin demandé est une ancienne adresse, redirige
 * vers l'adresse actuelle.
 *
 * Catches 404s: if the requested path is a past address, redirects to the
 * current one.
 *
 * L'historique n'est consulté que lorsque la réponse est un 404. Une page qui
 * existe ne déclenche donc aucune requête supplémentaire : c'est la condition
 * pour laisser ce middleware en place en production.
 */
final class RedirectToCurrentSlug
{
    /** Les seules méthodes qu'un moteur ou un vieux lien emploient. */
    private const METHODS = ['GET', 'HEAD'];

    public function __construct(
        private readonly SlugHistory $history,
        private readonly int $status = 301,
        /** Une portée fixe, ou une fonction qui la tire de la requête. */
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
     * La chaîne de requête suit la redirection : ?page=2, les paramètres de
     * campagne, tout ce que le visiteur avait dans son lien.
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
