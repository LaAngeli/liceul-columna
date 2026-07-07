<?php

use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\BibliotecaController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CabinetController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\ForcedPasswordController;
use App\Http\Controllers\ForcedTwoFactorController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\PrivacyConsentController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\TwoFactorEmailChallengeController;
use App\Http\Controllers\TwoFactorEmailSetupController;
use App\Http\Controllers\VisitController;
use App\Http\Middleware\EnsureFamilyCabinet;
use App\Http\Middleware\SetPublicLocale;
use App\Http\Middleware\SetUserLocale;
use App\Support\Locale;
use App\Support\RouteSlugs;
use App\Support\TeacherDirectory;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site public (Inertia/React) — migrare de pe columna.org.md
|--------------------------------------------------------------------------
| Multilingv: RO la root (păstrează URL-urile migrate), RU/EN cu prefix de
| URL (/ru, /en) ȘI slug tradus (vezi App\Support\RouteSlugs — traducere pe
| segment, completată treptat). Rutele publice se înregistrează o dată în
| `$publicRoutes` (parametrizat cu limba curentă) și se montează pentru
| fiecare limbă; URI-urile literale trec prin `$p()`, care traduce fiecare
| segment pentru limba activă (identitate pt. RO și pt. segmentele fără
| traducere încă în hartă). Conținutul se rezolvă la cerere.
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

$publicRoutes = function (string $locale): void {
    // Traduce un path canonic RO în slug-ul limbii curent montate (identitate pt. RO
    // și pt. segmentele fără traducere încă în App\Support\RouteSlugs).
    $p = fn (string $path): string => RouteSlugs::translatePath($path, $locale);

    Route::get('/', [HomeController::class, 'index'])->name('home');

    // Despre liceu
    Route::inertia($p('/scrisoarea-directorului'), 'public/scrisoarea-directorului')->name('scrisoarea-directorului'); // bespoke: scrisoare editorial cu portret + signature ceremonial
    Route::inertia($p('/de-ce-columna'), 'public/de-ce-columna')->name('de-ce-columna'); // pagină-fanion bespoke (hero + constelația valorilor)
    Route::inertia($p('/filosofia-liceului'), 'public/filosofia-liceului')->name('filosofia-liceului'); // bespoke: manifest editorial (principii pe navy + convingere cu foto)
    Route::inertia($p('/acreditari'), 'public/acreditari')->name('acreditari'); // bespoke: certificatele reale (înregistrare + acreditare ANACEC) ca documente
    Route::inertia($p('/istorie'), 'public/istorie')->name('istorie'); // bespoke: timeline editorial „Din 1998 până azi"

    // Structura școlii
    Route::inertia($p('/structura-scolii'), 'public/structura-scolii')->name('structura-scolii'); // bespoke: 3 trepte (foto reală + numeral roman + body verbatim)
    Route::inertia($p('/scoala-primara'), 'public/scoala-primara')->name('scoala-primara'); // bespoke: identitate + 7 arii curriculare (Curriculum Național) + PDF descărcabil + dotări + galerie
    Route::inertia($p('/scoala-gimnaziala'), 'public/scoala-gimnaziala')->name('scoala-gimnaziala'); // bespoke: identitate + 13 discipline (PDF curriculum descărcabil local) + dotări + galerie
    publicPage($p('/scoala-gimnaziala/curriculum'), 'scoala-gimnaziala.curriculum', 'Curriculum — gimnaziu', [['title' => 'Structura școlii', 'href' => '/structura-scolii'], ['title' => 'Școala gimnazială', 'href' => '/scoala-gimnaziala'], ['title' => 'Curriculum']]);
    publicPage($p('/scoala-gimnaziala/dotari'), 'scoala-gimnaziala.dotari', 'Dotări — gimnaziu', [['title' => 'Structura școlii', 'href' => '/structura-scolii'], ['title' => 'Școala gimnazială', 'href' => '/scoala-gimnaziala'], ['title' => 'Dotări']]);
    publicPage($p('/scoala-gimnaziala/galerie'), 'scoala-gimnaziala.galerie', 'Galerie — gimnaziu', [['title' => 'Structura școlii', 'href' => '/structura-scolii'], ['title' => 'Școala gimnazială', 'href' => '/scoala-gimnaziala'], ['title' => 'Galerie']]);
    Route::inertia($p('/scoala-liceala'), 'public/scoala-liceala')->name('scoala-liceala'); // bespoke: identitate + 10 discipline pe arii + Cambridge English + dotări + galerie
    publicPage($p('/scoala-liceala/curriculum'), 'scoala-liceala.curriculum', 'Curriculum — liceu', [['title' => 'Structura școlii', 'href' => '/structura-scolii'], ['title' => 'Școala liceală', 'href' => '/scoala-liceala'], ['title' => 'Curriculum']]);
    publicPage($p('/scoala-liceala/dotari'), 'scoala-liceala.dotari', 'Dotări — liceu', [['title' => 'Structura școlii', 'href' => '/structura-scolii'], ['title' => 'Școala liceală', 'href' => '/scoala-liceala'], ['title' => 'Dotări']]);
    publicPage($p('/scoala-liceala/galerie'), 'scoala-liceala.galerie', 'Galerie — liceu', [['title' => 'Structura școlii', 'href' => '/structura-scolii'], ['title' => 'Școala liceală', 'href' => '/scoala-liceala'], ['title' => 'Galerie']]);

    // Personal — pagină-listă + fișe individuale (URL-uri vechi păstrate; numele de persoane
    // NU se traduc — slug-ul rămâne identic pe toate limbile, la fel ca înainte).
    Route::inertia($p('/personal'), 'public/personal', [
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

    // Actualități + Blog (articole din DB) și galerie — slug-urile articolelor (DB) rămân
    // neschimbate pe toate limbile (conținut, nu structură de site; scope separat).
    Route::get($p('/actualitati-si-evenimente'), [BlogController::class, 'index'])->defaults('category', 'actualitati')->name('actualitati-si-evenimente');
    Route::get($p('/blog'), [BlogController::class, 'index'])->defaults('category', 'blog')->name('blog');
    Route::get('/articol/{post:slug}', [BlogController::class, 'show'])->name('articol');
    Route::get($p('/galerie'), [GalleryController::class, 'index'])->name('galerie'); // galerie foto interactivă (lightbox)

    // Calendar / Orare
    Route::get($p('/calendar'), [CalendarController::class, 'index'])->name('calendar'); // explorator interactiv de orare
    publicPage($p('/orarul-lectiilor'), 'orarul-lectiilor', 'Orarul lecțiilor', [['title' => 'Calendar', 'href' => '/calendar'], ['title' => 'Orarul lecțiilor']]);
    publicPage($p('/orarul-sunetelor'), 'orarul-sunetelor', 'Orarul sunetelor', [['title' => 'Calendar'], ['title' => 'Orarul sunetelor']]);
    publicPage($p('/orarul-examenelor'), 'orarul-examenelor', 'Orarul examenelor', [['title' => 'Calendar'], ['title' => 'Orarul examenelor']]);
    publicPage($p('/orarul-ess'), 'orarul-ess', 'Orarul ESS (teze semestriale)', [['title' => 'Calendar'], ['title' => 'Orarul ESS']]);
    publicPage($p('/orarul-pretestarilor'), 'orarul-pretestarilor', 'Orarul pretestărilor', [['title' => 'Calendar'], ['title' => 'Orarul pretestărilor']]);
    publicPage($p('/cursuri-de-pregatire-pentru-examene'), 'cursuri-de-pregatire-pentru-examene', 'Pregătire pentru examene', [['title' => 'Calendar'], ['title' => 'Pregătire pentru examene']]);
    publicPage($p('/orarul-cpae'), 'orarul-cpae', 'Orarul CPAE', [['title' => 'Calendar'], ['title' => 'Orarul CPAE']]);
    publicPage($p('/orar-recuperari'), 'orar-recuperari', 'Orar recuperări', [['title' => 'Calendar'], ['title' => 'Orar recuperări']]);
    publicPage($p('/sedintele-cu-parintii'), 'sedintele-cu-parintii', 'Ședințele cu părinții', [['title' => 'Calendar'], ['title' => 'Ședințele cu părinții']]);

    // Admitere + formular de înscriere
    Route::inertia($p('/admitere'), 'public/admitere')->name('admitere'); // bespoke: 2 etape (verbatim) + telefon programare + 8 FAQ accordion + CTA înscriere
    Route::inertia($p('/taxe'), 'public/taxe')->name('taxe'); // bespoke: cadru (fără cifre publicate) + ce include taxa + 2 pași pentru grila oficială + 3 principii (reducere 15% / plată / fără taxă înmatriculare)
    Route::inertia($p('/intrebari-frecvente'), 'public/intrebari-frecvente')->name('intrebari-frecvente'); // bespoke: HUB FAQ (3 categorii + 6 întrebări verbatim accordion + 4 linkuri pagini dedicate + CTA)
    Route::get($p('/inregistrarea-student'), [AdmissionController::class, 'create'])->name('inregistrarea-student'); // cerere de înmatriculare (date familie + copil, fără calendar)
    Route::post($p('/inregistrarea-student'), [AdmissionController::class, 'store']);
    Route::get($p('/programeaza-vizita'), [VisitController::class, 'create'])->name('programeaza-vizita'); // programare vizită (calendar + oră) — CTA principal navbar
    Route::post($p('/programeaza-vizita'), [VisitController::class, 'store']);

    // Meniu secundar
    Route::inertia($p('/centrul-de-evaluare-institutionala'), 'public/centrul-de-evaluare-institutionala')->name('centrul-de-evaluare-institutionala'); // bespoke: SIERȘ (motto + 4 carduri-listă + documente + transparență)
    Route::inertia($p('/extracurriculare'), 'public/extracurriculare')->name('extracurriculare'); // bespoke: CPAE (viziune + citat + obiective/direcții + 9 coordonatori de ateliere)
    Route::inertia($p('/consiliul-metodic'), 'public/consiliul-metodic')->name('consiliul-metodic'); // bespoke: rol + componența nominală (7 membri) + atribuții
    Route::inertia($p('/consiliul-scolar'), 'public/consiliul-scolar')->name('consiliul-scolar'); // bespoke: cele trei voci (elevi/părinți/cadre) + componența în curând
    Route::inertia($p('/cambridge-english-exam'), 'public/cambridge-english-exam')->name('cambridge-english-exam'); // bespoke: centru autorizat din 2019 + 3 pachete de curs (tarife verbatim)
    Route::get($p('/biblioteca-online'), [BibliotecaController::class, 'index'])->name('biblioteca-online'); // pagină interactivă dedicată
    Route::inertia($p('/tabara-de-vara'), 'public/tabara-de-vara')->name('tabara-de-vara'); // bespoke placeholder: concept + 6 categorii orientative + status „în pregătire" + CTA secretariat
    Route::inertia($p('/sponsorizare'), 'public/sponsorizare')->name('sponsorizare'); // bespoke: Mecanism 2% (verbatim + IDNO 1004600000818 evidențiat) + 3 pași concreți + 4 link-uri oficiale + Donații directe + downloads CET18/Contract
    Route::inertia($p('/contacte'), 'public/contacte')->name('contacte'); // pagină de contact bespoke (split + hartă + formular)
    Route::post($p('/contacte'), [ContactController::class, 'store'])->middleware('throttle:6,1')->name('contacte.store');
    Route::get($p('/contacte/multumim'), [ContactController::class, 'thanks'])->name('contacte.thanks');
    // Pagini juridice (footer) — motorul generic PublicPageContent + slug tradus RU/EN.
    publicPage($p('/confidentialitate'), 'confidentialitate', 'Politica de confidențialitate', [['title' => 'Confidențialitate']], 'Cum protejăm datele cu caracter personal ale elevilor, părinților și vizitatorilor, conform Legii nr. 133/2011.');
    publicPage($p('/termeni-si-conditii'), 'termeni-si-conditii', 'Termeni și condiții', [['title' => 'Termeni și condiții']], 'Termenii de utilizare a site-ului columna.md și a serviciilor online ale Liceului Columna.');
    publicPage($p('/politica-cookies'), 'politica-cookies', 'Politica privind cookie-urile', [['title' => 'Politica cookie-uri']], 'Ce cookie-uri folosim, în ce scop și cum îți gestionezi preferințele.');
};

// RO la root + RU/EN cu prefix de URL (+ slug tradus, vezi RouteSlugs).
Route::middleware(SetPublicLocale::class)->group(fn () => $publicRoutes(Locale::default()));
foreach (Locale::prefixed() as $prefix) {
    Route::prefix($prefix)->name($prefix.'.')->middleware(SetPublicLocale::class)->group(fn () => $publicRoutes($prefix));
}

/*
|--------------------------------------------------------------------------
| Zona autentificată (cabinet personal) — limba din preferința userului
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', SetUserLocale::class])->group(function () {
    // EnsureFamilyCabinet redirecționează personalul la /admin — se aplică pe rutele GET care RANDEAZĂ
    // pagini de familie (gating UNIFORM, audit M-8/#23/#39/#24). NU pe acțiunile POST (au deja abort 403
    // în controller) și NU pe vizualizarea profilului unui elev / descărcările de PII (accesibile ȘI
    // administrației, cu gating propriu). Dashboard-ul se auto-redirecționează în controller.
    Route::get('dashboard', [CabinetController::class, 'index'])->name('dashboard');
    Route::get('cabinet/elev/{student}', [CabinetController::class, 'student'])->name('cabinet.student');
    Route::post('cabinet/elev/{student}/motivare', [CabinetController::class, 'requestMotivation'])->name('cabinet.motivation');
    Route::get('cabinet/motivare/{absenceMotivation}/document', [CabinetController::class, 'downloadMotivationDocument'])->name('cabinet.motivation.document');
    Route::post('cabinet/elev/{student}/confirm-statut', [CabinetController::class, 'acknowledgeStatus'])->name('cabinet.status.acknowledge');

    // Comunicare (spec §4): inbox + trimitere filtrată ierarhic + răspuns în fir.
    Route::get('cabinet/mesaje', [MessagesController::class, 'index'])
        ->middleware(EnsureFamilyCabinet::class)
        ->name('cabinet.messages');
    Route::post('cabinet/mesaje', [MessagesController::class, 'send'])->name('cabinet.messages.send');
    Route::post('cabinet/mesaje/{message}/raspunde', [MessagesController::class, 'reply'])->name('cabinet.messages.reply');
    Route::post('cabinet/mesaje/{message}/citit', [MessagesController::class, 'markRead'])->name('cabinet.messages.read');

    // Cereri tipice (spec §4.3): depunere (→ PDF, secretariat) + descărcare PDF privat.
    Route::post('cabinet/elev/{student}/cereri', [CabinetController::class, 'requestDocument'])->name('cabinet.requests.store');
    Route::get('cabinet/cereri/{documentRequest}/pdf', [CabinetController::class, 'downloadRequest'])->name('cabinet.requests.pdf');

    // Notificări (spec §5): inbox in-app + setări (contacte + matrice canal × tip).
    Route::get('cabinet/notificari', [NotificationsController::class, 'index'])
        ->middleware(EnsureFamilyCabinet::class)
        ->name('cabinet.notifications');
    Route::post('cabinet/notificari/citeste-tot', [NotificationsController::class, 'markAllRead'])->name('cabinet.notifications.read-all');
    Route::post('cabinet/notificari/{notification}/citit', [NotificationsController::class, 'markRead'])->name('cabinet.notifications.read');
    Route::get('cabinet/notificari/setari', [NotificationsController::class, 'settings'])
        ->middleware(EnsureFamilyCabinet::class)
        ->name('cabinet.notifications.settings');
    Route::put('cabinet/notificari/setari', [NotificationsController::class, 'updateSettings'])->name('cabinet.notifications.settings.update');

    // Profil (DOAR vizualizare): datele contului + situația elevului/copiilor. Fără editare/ștergere
    // a contului din cabinet — gestiunea conturilor revine personalului (UserResource, după ierarhie).
    // `password.confirm`: profilul e și suprafața de securitate (2FA); cu flag-ul setat la login
    // (AppServiceProvider) confirmarea e de regulă tăcută, iar „intended"-ul rămâne o pagină GET.
    Route::get('cabinet/profil', [CabinetController::class, 'profile'])
        ->middleware(['password.confirm', EnsureFamilyCabinet::class])
        ->name('cabinet.profile');
});

// Descărcarea documentelor din bibliotecă — acces RE-CONFIRMAT pe server la fiecare cerere pe baza
// rolului real (spec §1). Servește atât personalul (panou), cât și familia (cabinet) — de-aceea nu
// e sub gardul „doar familie" al cabinetului. Documentele individuale (generate) au rutele lor proprii.
Route::middleware(['auth', SetUserLocale::class])
    ->get('documente/{document}/descarca', [DocumentDownloadController::class, 'download'])
    ->name('documents.download');

// Schimbarea obligatorie a parolei (userii migrați) — doar `auth`.
Route::middleware(['auth', SetUserLocale::class])->group(function () {
    Route::get('schimbare-parola', [ForcedPasswordController::class, 'edit'])->name('password.change');
    Route::put('schimbare-parola', [ForcedPasswordController::class, 'update'])->name('password.change.update');

    // Luare la cunoștință a notei de informare (Legea 133/2011 §7) — blocant pentru elev/părinte.
    Route::get('consimtamant', [PrivacyConsentController::class, 'show'])->name('privacy.consent');
    Route::post('consimtamant', [PrivacyConsentController::class, 'store'])->name('privacy.consent.store');
});

// Configurarea OBLIGATORIE a 2FA (gate-ul EnsureTwoFactorEnrolled trimite aici; ruta e exceptată
// în gate). Sub password.confirm ca „intended"-ul confirmării să fie mereu ACEASTĂ pagină GET —
// confirmarea e de regulă tăcută: flag-ul se setează la login (vezi AppServiceProvider).
Route::middleware(['auth', 'password.confirm', SetUserLocale::class])->group(function () {
    Route::get('configurare-2fa', [ForcedTwoFactorController::class, 'show'])->name('two-factor.setup');
});

// 2FA pe email — ACTIVARE (autentificat + parola confirmată, ca endpoint-urile 2FA Fortify).
Route::middleware(['auth', 'password.confirm', SetUserLocale::class])->group(function () {
    Route::post('user/two-factor-email/send', [TwoFactorEmailSetupController::class, 'send'])->name('two-factor-email.send');
    Route::post('user/two-factor-email/confirm', [TwoFactorEmailSetupController::class, 'confirm'])->name('two-factor-email.confirm');
    Route::delete('user/two-factor-email', [TwoFactorEmailSetupController::class, 'destroy'])->name('two-factor-email.destroy');
});

// 2FA pe email — CHALLENGE la login (sesiune parțială login.id, PRE-autentificare; guest +
// rate-limiter-ul `two-factor` al Fortify, per login.id).
Route::middleware(['guest:web', 'throttle:two-factor', SetUserLocale::class])->group(function () {
    Route::post('two-factor-challenge/email/send', [TwoFactorEmailChallengeController::class, 'send'])->name('two-factor-email.challenge.send');
    Route::post('two-factor-challenge/email', [TwoFactorEmailChallengeController::class, 'verify'])->name('two-factor-email.challenge.verify');
});
