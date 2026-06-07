<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection($this->connection())->create('trail_aggregates', function (Blueprint $table) {
            $table->id();
            $table->string('period');
            $table->timestamp('bucket')->index();
            $table->string('name')->index();
            $table->unsignedBigInteger('count')->default(0);
            $table->unsignedBigInteger('unique_subjects')->default(0);
            $table->decimal('sum_value', 20, 4)->nullable();
            $table->timestamps();

            $table->unique(['period', 'bucket', 'name']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection())->dropIfExists('trail_aggregates');
    }

    protected function connection(): ?string
    {
        return config('trail.connection');
    }
};
