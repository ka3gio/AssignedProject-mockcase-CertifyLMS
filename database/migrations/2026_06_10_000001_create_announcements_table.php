<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title', 200);
            $table->text('body');
            $table->string('target_type', 30);
            $table->foreignUlid('target_certification_id')
                ->nullable()
                ->constrained('certifications')
                ->restrictOnDelete();
            $table->foreignUlid('target_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUlid('created_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->unsignedInteger('dispatched_count');
            $table->timestamp('dispatched_at');
            $table->timestamps();

            $table->index(['target_type', 'dispatched_at']);
            $table->index('dispatched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
