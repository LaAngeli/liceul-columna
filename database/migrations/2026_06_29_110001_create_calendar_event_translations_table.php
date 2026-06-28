<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traducerile RU/EN ale evenimentelor de calendar (titlu/descriere). Câmpuri nullable → fallback la
 * sursa RO de pe calendar_events. Oglindește tiparul post_translations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_event_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['calendar_event_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_translations');
    }
};
