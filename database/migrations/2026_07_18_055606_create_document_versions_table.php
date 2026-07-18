<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versionare REALĂ a documentelor statice (Documente utile, Faza 4): la înlocuirea fișierului,
 * versiunea anterioară nu se mai pierde — fișierul rămâne pe disc, iar metadatele lui (cine l-a
 * urcat, ce etichetă de versiune purta) intră aici. Istoricul e vizibil doar administratorilor
 * bibliotecii; familia/staff-ul de rând văd mereu doar versiunea curentă.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            // Fără cascadeOnDelete: igiena (fișier + rând) se face explicit în Document::forceDeleting,
            // ca fișierele versiunilor să poată fi șterse de pe disc ÎNAINTE să dispară rândurile.
            $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('version_label')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
