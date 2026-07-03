<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_translations', function (Blueprint $table): void {
            // Slug per limbă (URL localizat). E nullable ca migrarea să treacă peste înregistrări
            // existente; formularul Studio îl cere obligatoriu la creare/editare.
            $table->string('slug')->nullable()->after('locale');
            $table->unique(['locale', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('post_translations', function (Blueprint $table): void {
            $table->dropUnique(['locale', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
