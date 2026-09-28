<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Laravel\Console;

use Illuminate\Console\Command;
use Kaveraa\SlugHistory\PastSlug;
use Kaveraa\SlugHistory\SlugHistory;

/**
 * php artisan slugs:history "App\Models\Article" 12
 */
final class HistoryCommand extends Command
{
    protected $signature = 'slugs:history {type : La classe du contenu} {id : Son identifiant}';

    protected $description = 'Liste les anciennes adresses d\'un contenu';

    public function handle(SlugHistory $history): int
    {
        $type = (string) $this->argument('type');
        $id = (string) $this->argument('id');

        $rows = $history->allFor($type, $id);

        if ($rows === []) {
            $this->info(sprintf('Aucune ancienne adresse pour %s #%s.', $type, $id));

            return self::SUCCESS;
        }

        $this->table(
            ['Ancienne adresse', 'Portée', 'Mène vers', 'Retenue le'],
            array_map(static fn (PastSlug $past): array => [
                $past->slug,
                $past->scope === '' ? '-' : $past->scope,
                $past->currentSlug,
                $past->rememberedAt?->format('Y-m-d H:i') ?? '-',
            ], $rows),
        );

        $this->info(sprintf('%d ancienne(s) adresse(s) pour %s #%s.', count($rows), $type, $id));

        return self::SUCCESS;
    }
}
