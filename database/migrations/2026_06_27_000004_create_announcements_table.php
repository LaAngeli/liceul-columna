<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anunțuri broadcast ale conducerii (spec §4): se publică → ajung ca notificare la toate familiile
 * (inbox + email/social, după preferințe). Confirmarea de citire = `read_at` pe notificarea livrată;
 * `recipients_count` reține câtor li s-a trimis, ca să se poată afișa „citit X / Y".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
