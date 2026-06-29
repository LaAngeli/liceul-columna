<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canonizare orar (nedistructiv): leagă orarul publicabil de clasa reală printr-un FK opțional, în
 * loc ca identitatea clasei să existe doar în eticheta-text. Nu schimbă contractul public
 * (publicTablesFor expune tot doar label/headers/rows). nullOnDelete: ștergerea clasei dezleagă.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            $table->foreignId('school_class_id')->nullable()->after('label')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('school_class_id');
        });
    }
};
