<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Laravel\Console;

use Illuminate\Console\Command;

/**
 * php artisan slugs:install
 */
final class InstallCommand extends Command
{
    protected $signature = 'slugs:install';

    protected $description = 'Publie la configuration et la migration de slug-history';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'slug-history-config']);
        $this->call('vendor:publish', ['--tag' => 'slug-history-migrations']);

        $this->newLine();
        $this->info('Étapes suivantes :');
        $this->line('  1. Lancez : php artisan migrate');
        $this->line('  2. Ajoutez le trait Kaveraa\SlugHistory\Laravel\Concerns\HasSlugHistory à vos modèles');
        $this->line('  3. C\'est tout : la redirection est déjà en place');
        $this->newLine();
        $this->line('  Les anciennes adresses d\'un contenu : php artisan slugs:history "App\Models\Article" 12');
        $this->line('  Le ménage, si vous en voulez un : php artisan slugs:purge --older-than=365');

        return self::SUCCESS;
    }
}
