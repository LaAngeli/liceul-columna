<?php

use App\Models\Lesson;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Orarul structurat renunță la soft-delete (motivul complet: {@see Lesson}).
 *
 * Sloturile deja șterse se golesc definitiv ÎNAINTE de a scoate coloana: altfel ar reînvia toate
 * odată la eliminarea filtrului, iar cele care se suprapun peste sloturi recreate între timp ar
 * încălca indexul unic `lesson_class_slot_unique`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lessons', 'deleted_at')) {
            return;
        }

        DB::table('lessons')->whereNotNull('deleted_at')->delete();

        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn('deleted_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('lessons', 'deleted_at')) {
            return;
        }

        Schema::table('lessons', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }
};
