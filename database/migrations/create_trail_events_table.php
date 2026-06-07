<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection($this->connection())->create('trail_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('name')->index();
            $table->nullableMorphs('subject');
            $table->string('session_id')->nullable()->index();
            $table->json('properties')->nullable();
            $table->json('context')->nullable();
            $table->decimal('value', 20, 4)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id', 'occurred_at']);
            $table->index(['name', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection())->dropIfExists('trail_events');
    }

    protected function connection(): ?string
    {
        return config('trail.connection');
    }
};
