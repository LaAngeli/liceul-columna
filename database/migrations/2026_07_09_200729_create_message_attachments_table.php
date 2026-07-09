<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atașamentele unui mesaj (fișiere/imagini). Stocate PRIVAT (`storage/app/private`) — pot conține
 * PII de minori — și descărcabile doar de participanții firului, prin rută autentificată. NU se
 * păstrează conținutul în DB, doar metadatele + calea pe disc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 191);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
    }
};
