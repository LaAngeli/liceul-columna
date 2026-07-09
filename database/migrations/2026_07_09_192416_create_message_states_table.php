<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Starea PER-UTILIZATOR a unui fir de mesaje (poșta internă a cabinetului): marcat cu stea
 * (preferat) și/sau mutat în coș. Un mesaj are 2 participanți, iar fiecare își gestionează
 * independent propria „cutie" — de-asta starea NU stă pe `messages` (globală), ci aici, cheiată
 * pe (mesajul-rădăcină al firului, utilizator). Șters de un participant ≠ șters pentru celălalt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_states', function (Blueprint $table) {
            $table->id();
            // `message_id` = RĂDĂCINA firului (parent_id IS NULL). Starea se aplică întregii conversații.
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('starred_at')->nullable();
            $table->timestamp('trashed_at')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'user_id']);
            $table->index(['user_id', 'trashed_at']);
            $table->index(['user_id', 'starred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_states');
    }
};
