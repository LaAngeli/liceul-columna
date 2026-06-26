<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Foaia matricolă: arhiva istorică a mediilor unui elev, pe treaptă (clasa 1-12)
        // și perioadă (semestrul I/II sau media anuală). NU se leagă de clasa/semestrul
        // anului curent — e un istoric care acoperă toți anii de studiu.
        Schema::create('academic_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('grade_level'); // treapta (1-12), legacy `cl`
            $table->unsignedTinyInteger('period'); // 1=Sem I, 2=Sem II, 3=anuală, legacy `sem`
            $table->decimal('value', 4, 2)->nullable(); // nota
            $table->string('calificativ', 10)->nullable(); // calif
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'grade_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_records');
    }
};
