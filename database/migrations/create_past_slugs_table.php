<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La table des anciennes adresses.
 *
 * The table of past addresses.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            $table->id();

            // Le contenu concerné : sa classe et son identifiant. L'identifiant
            // est une chaîne pour accueillir aussi bien un entier qu'un UUID.
            $table->string('subject_type', 191);
            $table->string('subject_id', 191);

            // L'ancienne adresse, celle qui traîne dans les liens et les moteurs.
            $table->string('slug', 191);

            // L'adresse actuelle : on redirige sans avoir à charger le contenu.
            $table->string('current_slug', 191);

            // La portée : la langue, la rubrique parente, ce qui rend l'adresse
            // unique. Elle n'est surtout PAS nullable, et vaut la chaîne vide
            // quand il n'y a pas de portée. Raison : dans l'index unique
            // ci-dessous, MySQL comme PostgreSQL considèrent que NULL n'est
            // jamais égal à NULL. Une colonne nullable laisserait donc entrer
            // dix fois la même adresse, et l'unicité ne voudrait plus rien dire.
            $table->string('scope', 191)->default('');

            // Toujours renseignée : c'est elle que regarde slugs:purge. La table
            // Symfony est identique, pour que les deux frameworks partagent le schéma.
            $table->timestamp('created_at');

            // Le coeur du contrat : une adresse ne mène qu'à un seul contenu.
            $table->unique(['slug', 'scope']);

            // La recherche par contenu : retarget, forget, la liste des adresses.
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        $configured = function_exists('config') ? config('slug-history.table', 'past_slugs') : null;

        return is_string($configured) && $configured !== '' ? $configured : 'past_slugs';
    }
};
