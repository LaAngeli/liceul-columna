<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipul evaluării (§2.4): curentă / ESI / teză(ESS). Notele existente (legacy)
     * devin „curentă" — nu avem marcaj de teză sigur în datele vechi.
     */
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->string('evaluation_type', 16)->default('curenta')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn('evaluation_type');
        });
    }
};
