<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biblioteca „Documente utile" (anexă tehnică §1–§3): documente STATICE (fișiere încărcate,
     * versionate) cu acces impus pe SERVER pe baza rolului. Documentele GENERATE (rapoarte, cereri)
     * rămân produse de funcțiile existente; aici stocăm doar biblioteca statică.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();

            $table->string('title');
            $table->text('description')->nullable();

            // Organizare + acces (spec §1/§2).
            $table->string('category');                        // DocumentCategory: rapoarte/cereri/…
            $table->string('access_level')->default('public'); // public / role_specific / individual
            $table->json('visible_roles')->nullable();         // list<UserRole> — folosit la access_level = role_specific
            $table->string('source')->default('static');       // static / generated

            // Fișierul (stocat PRIVAT pe disk-ul `local`; descărcare doar prin rută gardată).
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();           // numele original, pentru descărcare
            $table->unsignedBigInteger('file_size')->nullable(); // bytes
            $table->string('mime_type')->nullable();

            $table->string('version')->nullable();             // versionare simplă (ex. „ed. 2026")
            $table->boolean('is_published')->default(false);

            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'access_level']);
            $table->index('is_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
