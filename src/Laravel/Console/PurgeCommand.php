<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Laravel\Console;

use Illuminate\Console\Command;
use Kaveraa\SlugHistory\SlugHistory;

/**
 * php artisan slugs:purge --older-than=365
 *
 * Without the option, the duration comes from the configuration. With no
 * duration anywhere, we guess nothing: keeping forever is a valid choice.
 */
final class PurgeCommand extends Command
{
    protected $signature = 'slugs:purge {--older-than= : Nombre de jours au-delà duquel une adresse est oubliée}';

    protected $description = 'Oublie les anciennes adresses trop vieilles';

    public function handle(SlugHistory $history): int
    {
        $given = $this->option('older-than');
        $value = $given === null || $given === '' ? $this->config() : $given;

        if ($value === null || $value === '') {
            $this->error('Aucune durée de conservation.');
            $this->line('Donnez --older-than=<jours>, ou renseignez "keep_for_days" dans config/slug-history.php.');

            return self::FAILURE;
        }

        if (!is_numeric($value)) {
            $this->error(sprintf('"%s" n\'est pas un nombre de jours.', (string) $value));

            return self::FAILURE;
        }

        $days = (int) $value;

        if ($days < 1) {
            $this->error(sprintf('%d jour(s) : il en faut au moins 1.', $days));
            $this->line('Une durée nulle ou négative effacerait tout l\'historique.');

            return self::FAILURE;
        }

        $gone = $history->purgeOlderThan($days);

        $this->info(sprintf(
            '%d ancienne(s) adresse(s) oubliée(s), au-delà de %d jour(s).',
            $gone,
            $days,
        ));

        return self::SUCCESS;
    }

    private function config(): mixed
    {
        return $this->laravel->make('config')->get('slug-history.keep_for_days');
    }
}
