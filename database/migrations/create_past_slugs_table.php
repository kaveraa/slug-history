<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The table of past addresses.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            $table->id();

            // The content this row is about: its class and its identifier. The
            // identifier is a string so it can hold an integer or a UUID.
            $table->string('subject_type', 191);
            $table->string('subject_id', 191);

            // The old address, the one still found in links and search engines.
            $table->string('slug', 191);

            // The current address: we redirect without loading the content.
            $table->string('current_slug', 191);

            // The scope: the language, the parent section, what makes the address
            // unique. It is NOT nullable, and it is the empty string when there
            // is no scope. Reason: in the unique index below, MySQL and
            // PostgreSQL both consider that NULL is never equal to NULL. A
            // nullable column would let the same address in ten times, and
            // uniqueness would mean nothing any more.
            $table->string('scope', 191)->default('');

            // Always filled: this is what slugs:purge looks at. The Symfony table
            // is the same, so that both frameworks share the schema.
            $table->timestamp('created_at');

            // The heart of the contract: one address leads to only one content.
            $table->unique(['slug', 'scope']);

            // Lookup by content: retarget, forget, the list of addresses.
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
