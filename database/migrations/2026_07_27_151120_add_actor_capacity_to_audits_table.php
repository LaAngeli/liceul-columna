<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CALITATEA sub care s-a acționat, nu doar identitatea (spec §3.1: „fiecare acțiune este
 * înregistrată sub rolul activ").
 *
 * Până acum jurnalul reținea DOAR `user_id`, iar fișa afișa rolul CURENT al contului — deci un
 * profesor promovat director apărea, retroactiv, ca director în toate intrările lui vechi. Mai
 * grav pentru dirigenție: dreptul vine din desemnarea `homeroom_teacher_id`, care se reatribuie
 * între ani, așa că după reatribuire nu se mai putea reconstitui de unde venise dreptul.
 *
 * Ambele coloane sunt INSTANTANEE, scrise la momentul acțiunii și niciodată recalculate:
 * - `actor_role`     — valoarea rolului contului ATUNCI (ex. `profesor`), nu eticheta tradusă;
 * - `actor_capacity` — funcția efectivă ATUNCI (ex. `Diriginte: XI R`), null când lipsește.
 *
 * Rândurile istorice rămân null: nu inventăm retroactiv un context pe care nu l-am consemnat.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $table = config('audit.drivers.database.table', 'audits');

        Schema::connection($connection)->table($table, function (Blueprint $table): void {
            $table->string('actor_role')->nullable()->after('event');
            $table->string('actor_capacity')->nullable()->after('actor_role');

            // Investigare pe calitate: „ce au făcut diriginții în perioada X?" — filtrul din
            // jurnal lovește direct coloana, alături de intervalul de timp deja indexat.
            $table->index(['actor_role', 'created_at'], 'audits_actor_role_created_at_index');
        });
    }

    public function down(): void
    {
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $table = config('audit.drivers.database.table', 'audits');

        Schema::connection($connection)->table($table, function (Blueprint $table): void {
            $table->dropIndex('audits_actor_role_created_at_index');
            $table->dropColumn(['actor_role', 'actor_capacity']);
        });
    }
};
