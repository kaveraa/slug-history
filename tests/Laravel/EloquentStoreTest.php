<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Laravel;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Kaveraa\SlugHistory\Laravel\EloquentStore;
use Kaveraa\SlugHistory\Store;
use Kaveraa\SlugHistory\Tests\Support\StoreContract;

/**
 * The Eloquent driver keeps the same contract as the in-memory store.
 *
 * No Testbench here: the contract is a plain PHPUnit TestCase, and a table
 * on SQLite is enough to run it.
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

        // The same table as the published migration: scope not nullable, unique
        // index on (slug, scope).
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
