<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Laravel;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Kaveraa\SlugHistory\Laravel\EloquentStore;
use Kaveraa\SlugHistory\Store;
use Kaveraa\SlugHistory\Tests\Support\StoreContract;

/**
 * Le pilote Eloquent tient le même contrat que le rangement en mémoire.
 *
 * Pas de Testbench ici : le contrat est un simple TestCase PHPUnit, et une
 * table sur SQLite suffit à le jouer.
 */
final class EloquentStoreTest extends StoreContract
{
    private Capsule $capsule;

    private EloquentStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capsule = new Capsule();
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        // La même table que la migration publiée : scope non nullable, index
        // unique sur (slug, scope).
        $this->capsule->getConnection()->getSchemaBuilder()->create('past_slugs', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type', 191);
            $table->string('subject_id', 191);
            $table->string('slug', 191);
            $table->string('current_slug', 191);
            $table->string('scope', 191)->default('');
            $table->timestamp('created_at')->nullable();
            $table->unique(['slug', 'scope']);
            $table->index(['subject_type', 'subject_id']);
        });

        $this->store = new EloquentStore($this->capsule->getDatabaseManager(), 'past_slugs');
    }

    protected function tearDown(): void
    {
        $this->capsule->getDatabaseManager()->disconnect();

        parent::tearDown();
    }

    protected function store(): Store
    {
        return $this->store;
    }
}
