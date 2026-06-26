<?php

use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CabinetController;
use App\Http\Controllers\ForcedPasswordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\PublicPageController;
use App\Http\Middleware\SetPublicLocale;
use App\Http\Middleware\SetUserLocale;
use App\Support\Locale;
use App\Support\TeacherDirectory;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site public (Inertia/React) — migrare de pe columna.org.md
|--------------------------------------------------------------------------
| Multilingv: RO la root (păstrează URL-urile migrate), RU/EN cu prefix de
| URL (/ru, /en). Rutele publice se înregistrează o dată în `$publicRoutes`
| și se montează pentru fiecare limbă. Conținutul se rezolvă la cerere.
*/

if (! function_exists('publicPage')) {
    /**
     * Helper local pentru o pagină publică „schelet".
     *
     * @param  list<array{title: string, href?: string}>  $breadcrumbs
     */
    function publicPage(string $uri, string $name, string $title, array $breadcrumbs = [], ?string $description = null, bool $hasDownloads = false): void
    {
        Route::get($uri, [PublicPageController::class, 'show'])
            ->defaults('page', $name)
            ->defaults('pageTitle', $title)
            ->defaults('breadcrumbs', $breadcrumbs)
            ->defaults('description', $description)
            ->defaults('hasDownloads', $hasDownloads)
            ->name($name);
    }
}

// Comutarea limbii (cookie + preferința userului) — sursă unică pentru site, panou, cabinet.
Route::get('set-locale/{locale}', [LocaleController::class, 'switch'])->name('set-locale');

$publicRoutes = function (): void {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    // Despre liceu
    publicPage('/scrisoarea-directorului', 'scrisoarea-directorului', 'Scrisoarea directorului', [['title' => 'Despre liceu'], ['title' => 'Scrisoarea directorului']]);
    publicPage('/de-ce-columna', 'de-ce-columna', 'De ce Columna?', [['title' => 'Despre liceu'], ['title' => 'De ce Columna?']]);
    publicPage('/filosofia-liceului', 'filosofia-liceului', 'Filosofia liceului', [['title' => 'Despre liceu'], ['title' => 'Filosofia liceului']]);
    publicPage('/acreditari', 'acreditari', 'Acreditări', [['title' => 'Despre liceu'], ['title' => 'Acreditări']], 'Documentele de acreditare ale instituției.', true);

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

    // Personal — pagină-listă + fișe individuale (URL-uri vechi păstrate)
    Route::inertia('/personal', 'public/personal', [
        'groups' => TeacherDirectory::groups(),
    ])->name('personal');

    foreach (TeacherDirectory::profiles() as $teacherSlug => $teacher) {
        Route::inertia('/'.$teacherSlug, 'public/teacher', [
            'name' => $teacher['name'],
            'role' => $teacher['role'],
            'slug' => $teacherSlug,
            'photo' => $teacher['photo'] ?? null,
        ])->name('personal.'.$teacherSlug);
    }

    // Actualități + Blog (articole din DB) și galerie
    Route::get('/actualitati-si-evenimente', [BlogController::class, 'index'])->defaults('category', 'actualitati')->name('actualitati-si-evenimente');
    Route::get('/blog', [BlogController::class, 'index'])->defaults('category', 'blog')->name('blog');
    Route::get('/articol/{post:slug}', [BlogController::class, 'show'])->name('articol');
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

    // Admitere + formular de înscriere
    publicPage('/admitere', 'admitere', 'Admitere', [['title' => 'Admitere']], 'Pașii de înscriere la Liceul Columna.');
    Route::get('/inregistrarea-student', [AdmissionController::class, 'create'])->name('inregistrarea-student');
    Route::post('/inregistrarea-student', [AdmissionController::class, 'store']);

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
};

// RO la root + RU/EN cu prefix de URL.
Route::middleware(SetPublicLocale::class)->group($publicRoutes);
foreach (Locale::prefixed() as $prefix) {
    Route::prefix($prefix)->name($prefix.'.')->middleware(SetPublicLocale::class)->group($publicRoutes);
}

/*
|--------------------------------------------------------------------------
| Zona autentificată (cabinet personal) — limba din preferința userului
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', SetUserLocale::class])->group(function () {
    Route::get('dashboard', [CabinetController::class, 'index'])->name('dashboard');
    Route::get('cabinet/elev/{student}', [CabinetController::class, 'student'])->name('cabinet.student');
    Route::post('cabinet/elev/{student}/motivare', [CabinetController::class, 'requestMotivation'])->name('cabinet.motivation');

    // Comunicare (spec §4): inbox + trimitere filtrată ierarhic + răspuns în fir.
    Route::get('cabinet/mesaje', [MessagesController::class, 'index'])->name('cabinet.messages');
    Route::post('cabinet/mesaje', [MessagesController::class, 'send'])->name('cabinet.messages.send');
    Route::post('cabinet/mesaje/{message}/raspunde', [MessagesController::class, 'reply'])->name('cabinet.messages.reply');
    Route::post('cabinet/mesaje/{message}/citit', [MessagesController::class, 'markRead'])->name('cabinet.messages.read');

    // Cereri tipice (spec §4.3): depunere (→ PDF, secretariat) + descărcare PDF privat.
    Route::post('cabinet/elev/{student}/cereri', [CabinetController::class, 'requestDocument'])->name('cabinet.requests.store');
    Route::get('cabinet/cereri/{documentRequest}/pdf', [CabinetController::class, 'downloadRequest'])->name('cabinet.requests.pdf');
});

// Schimbarea obligatorie a parolei (userii migrați) — doar `auth`.
Route::middleware(['auth', SetUserLocale::class])->group(function () {
    Route::get('schimbare-parola', [ForcedPasswordController::class, 'edit'])->name('password.change');
    Route::put('schimbare-parola', [ForcedPasswordController::class, 'update'])->name('password.change.update');
});

require __DIR__.'/settings.php';
