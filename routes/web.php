<?php

use App\Http\Controllers\CabinetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site public (Inertia/React) — schelet pagini, migrare de pe columna.org.md
|--------------------------------------------------------------------------
| Paginile simple folosesc componenta generică `public/page` cu titlu +
| breadcrumbs trimise ca props. Vezi ANALIZA-SITE-VECHI.md pentru inventar.
*/

Route::inertia('/', 'public/home')->name('home');

if (! function_exists('publicPage')) {
    /**
     * Helper local pentru o pagină publică „schelet".
     *
     * @param  list<array{title: string, href?: string}>  $breadcrumbs
     */
    function publicPage(string $uri, string $name, string $title, array $breadcrumbs = [], ?string $description = null, bool $hasDownloads = false): void
    {
        Route::inertia($uri, 'public/page', [
            'title' => $title,
            'breadcrumbs' => $breadcrumbs,
            'description' => $description,
            'hasDownloads' => $hasDownloads,
        ])->name($name);
    }
}

// Despre liceu
publicPage('/scrisoarea-directorului', 'scrisoarea-directorului', 'Scrisoarea directorului', [['title' => 'Despre liceu'], ['title' => 'Scrisoarea directorului']]);
publicPage('/de-ce-columna', 'de-ce-columna', 'De ce Columna?', [['title' => 'Despre liceu'], ['title' => 'De ce Columna?']]);
publicPage('/filosofia-liceului', 'filosofia-liceului', 'Filosofia liceului', [['title' => 'Despre liceu'], ['title' => 'Filosofia liceului']]);
publicPage('/acreditari', 'acreditari', 'Acreditări', [['title' => 'Despre liceu'], ['title' => 'Acreditări']], 'Documentele de acreditare ale instituției.', true);
publicPage('/autorizare', 'autorizare', 'Autorizare', [['title' => 'Autorizare']], 'Autorizația de funcționare a instituției.', true);

// Structura școlii
publicPage('/structura-scolii', 'structura-scolii', 'Structura școlii', [['title' => 'Structura școlii']]);
publicPage('/scoala-primara', 'scoala-primara', 'Școala primară', [['title' => 'Structura școlii', 'href' => '/structura-scolii'], ['title' => 'Școala primară']]);
publicPage('/scoala-gimnaziala', 'scoala-gimnaziala', 'Școala gimnazială', [['title' => 'Structura școlii', 'href' => '/structura-scolii'], ['title' => 'Școala gimnazială']]);
publicPage('/scoala-gimnaziala/curriculum', 'scoala-gimnaziala.curriculum', 'Curriculum — gimnaziu', [['title' => 'Structura școlii', 'href' => '/structura-scolii'], ['title' => 'Școala gimnazială', 'href' => '/scoala-gimnaziala'], ['title' => 'Curriculum']]);
publicPage('/scoala-gimnaziala/dotari', 'scoala-gimnaziala.dotari', 'Dotări — gimnaziu', [['title' => 'Structura școlii', 'href' => '/structura-scolii'], ['title' => 'Școala gimnazială', 'href' => '/scoala-gimnaziala'], ['title' => 'Dotări']]);
publicPage('/scoala-gimnaziala/galerie', 'scoala-gimnaziala.galerie', 'Galerie — gimnaziu', [['title' => 'Structura școlii', 'href' => '/structura-scolii'], ['title' => 'Școala gimnazială', 'href' => '/scoala-gimnaziala'], ['title' => 'Galerie']]);
publicPage('/scoala-liceala', 'scoala-liceala', 'Școala liceală', [['title' => 'Structura școlii', 'href' => '/structura-scolii'], ['title' => 'Școala liceală']]);
publicPage('/scoala-liceala/curriculum', 'scoala-liceala.curriculum', 'Curriculum — liceu', [['title' => 'Structura școlii', 'href' => '/structura-scolii'], ['title' => 'Școala liceală', 'href' => '/scoala-liceala'], ['title' => 'Curriculum']]);
publicPage('/scoala-liceala/dotari', 'scoala-liceala.dotari', 'Dotări — liceu', [['title' => 'Structura școlii', 'href' => '/structura-scolii'], ['title' => 'Școala liceală', 'href' => '/scoala-liceala'], ['title' => 'Dotări']]);
publicPage('/scoala-liceala/galerie', 'scoala-liceala.galerie', 'Galerie — liceu', [['title' => 'Structura școlii', 'href' => '/structura-scolii'], ['title' => 'Școala liceală', 'href' => '/scoala-liceala'], ['title' => 'Galerie']]);

// Personal, actualități, blog, galerie
publicPage('/personal', 'personal', 'Personal', [['title' => 'Personal']], 'Echipa didactică a liceului.');
publicPage('/actualitati-si-evenimente', 'actualitati-si-evenimente', 'Actualități/Evenimente', [['title' => 'Actualități/Evenimente']]);
publicPage('/blog', 'blog', 'Blog', [['title' => 'Blog']]);
publicPage('/galerie', 'galerie', 'Galerie', [['title' => 'Galerie']]);

// Calendar / Orare
publicPage('/orarul-lectiilor', 'orarul-lectiilor', 'Orarul lecțiilor', [['title' => 'Calendar'], ['title' => 'Orarul lecțiilor']]);
publicPage('/orarul-sunetelor', 'orarul-sunetelor', 'Orarul sunetelor', [['title' => 'Calendar'], ['title' => 'Orarul sunetelor']]);
publicPage('/orarul-examenelor', 'orarul-examenelor', 'Orarul examenelor', [['title' => 'Calendar'], ['title' => 'Orarul examenelor']]);
publicPage('/orarul-ess', 'orarul-ess', 'Orarul ESS (teze semestriale)', [['title' => 'Calendar'], ['title' => 'Orarul ESS']]);
publicPage('/orarul-pretestarilor', 'orarul-pretestarilor', 'Orarul pretestărilor', [['title' => 'Calendar'], ['title' => 'Orarul pretestărilor']]);
publicPage('/cursuri-de-pregatire-pentru-examene', 'cursuri-de-pregatire-pentru-examene', 'Pregătire pentru examene', [['title' => 'Calendar'], ['title' => 'Pregătire pentru examene']]);
publicPage('/orarul-cpae', 'orarul-cpae', 'Orarul CPAE', [['title' => 'Calendar'], ['title' => 'Orarul CPAE']]);
publicPage('/orar-recuperari', 'orar-recuperari', 'Orar recuperări', [['title' => 'Calendar'], ['title' => 'Orar recuperări']]);
publicPage('/sedintele-cu-parintii', 'sedintele-cu-parintii', 'Ședințele cu părinții', [['title' => 'Calendar'], ['title' => 'Ședințele cu părinții']]);

// Admitere
publicPage('/admitere', 'admitere', 'Admitere', [['title' => 'Admitere']], 'Pașii de înscriere la Liceul Columna.');

// Meniu secundar
publicPage('/centrul-de-evaluare-institutionala', 'centrul-de-evaluare-institutionala', 'Centrul de Evaluare Instituțională', [['title' => 'CEI']], 'Ghidul și regulamentul de evaluare a rezultatelor școlare.', true);
publicPage('/extracurriculare', 'extracurriculare', 'Centrul de Promovare și Activități Extracurriculare', [['title' => 'CPAE']]);
publicPage('/consiliul-metodic', 'consiliul-metodic', 'Consiliul Metodic', [['title' => 'Consiliul Metodic']]);
publicPage('/consiliul-scolar', 'consiliul-scolar', 'Consiliul școlar', [['title' => 'Consiliul școlar']]);
publicPage('/cambridge-english-exam', 'cambridge-english-exam', 'Cambridge English Exam', [['title' => 'Cambridge English']]);
publicPage('/biblioteca-online', 'biblioteca-online', 'Biblioteca online', [['title' => 'Biblioteca online']], 'Cărți, curricula și ghiduri în format electronic.', true);
publicPage('/tabara-de-vara', 'tabara-de-vara', 'Tabără de vară', [['title' => 'Tabără de vară']]);
publicPage('/sponsorizare', 'sponsorizare', 'Sponsorizare', [['title' => 'Sponsorizare']]);
publicPage('/contacte', 'contacte', 'Contacte', [['title' => 'Contacte']]);

/*
|--------------------------------------------------------------------------
| Zona autentificată (cabinet personal)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [CabinetController::class, 'index'])->name('dashboard');
    Route::get('cabinet/elev/{student}', [CabinetController::class, 'student'])->name('cabinet.student');
});

require __DIR__.'/settings.php';
