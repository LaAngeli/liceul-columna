<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table): void {
            // Slug individual (opțional, unic): identificator uman al materialului, util pentru
            // referire în admin și pentru viitoare linkuri directe către material.
            $table->string('slug')->nullable()->after('title');
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
