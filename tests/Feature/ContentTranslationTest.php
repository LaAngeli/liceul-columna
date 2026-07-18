<?php

use App\Models\Post;
use App\Models\PostTranslation;
use App\Support\ContentTranslator;
use Inertia\Testing\AssertableInertia as Assert;

it('traduce șiruri de conținut cu fallback RO', function () {
    expect(ContentTranslator::string('Contacte', 'ru'))->toBe('Контакты')
        ->and(ContentTranslator::string('Contacte', 'en'))->toBe('Contacts')
        ->and(ContentTranslator::string('Contacte', 'ro'))->toBe('Contacte')
        ->and(ContentTranslator::string('___șir inexistent___', 'ru'))->toBe('___șir inexistent___');
});

it('sections() păstrează structura și traduce doar câmpurile de text', function () {
    $sections = [
        ['type' => 'cta', 'title' => 'Contacte', 'actions' => [
            ['label' => 'Contacte', 'href' => '/contacte', 'variant' => 'primary'],
        ]],
        ['type' => 'list', 'items' => ['Biologie', 'Chimie']],
    ];

    $ru = ContentTranslator::sections($sections, 'ru');

    expect($ru[0]['title'])->toBe('Контакты')
        ->and($ru[0]['actions'][0]['label'])->toBe('Контакты')
        ->and($ru[0]['actions'][0]['href'])->toBe('/contacte')
        ->and($ru[0]['actions'][0]['variant'])->toBe('primary')
        ->and($ru[1]['items'][0])->toBe('Биология')
        ->and($ru[1]['items'][1])->toBe('Химия');

    expect(ContentTranslator::sections($sections, 'ro'))->toBe($sections);
});

it('traduce numele disciplinelor (dicționar subjects) cu fallback RO', function () {
    expect(ContentTranslator::subject('Matematică', 'ru'))->toBe('Математика')
        ->and(ContentTranslator::subject('Matematică', 'en'))->toBe('Mathematics')
        ->and(ContentTranslator::subject('Limba și literatura română', 'en'))->toBe('Romanian Language and Literature')
        ->and(ContentTranslator::subject('Matematică', 'ro'))->toBe('Matematică')
        ->and(ContentTranslator::subject('Disciplină inexistentă', 'ru'))->toBe('Disciplină inexistentă');
});

it('scheduleCell traduce celulele compuse ale orarului pe prefix, cu fallback RO', function () {
    // Prefix-disciplină: numele tradus, restul (profesor, sală) neatins.
    expect(ContentTranslator::scheduleCell('Matematică Damian Iu. (s. 20)', 'ru'))->toBe('Математика Damian Iu. (s. 20)')
        // Cel mai LUNG prefix câștigă („Limba și literatura română", nu doar „Limba").
        ->and(ContentTranslator::scheduleCell('Limba și literatura română Russu I. (s. 19)', 'en'))
        ->toBe('Romanian Language and Literature Russu I. (s. 19)')
        // Eticheta primei coloane: „Lecția N interval" → prefixul tradus.
        ->and(ContentTranslator::scheduleCell('Lecția 1 08.15 – 09.00', 'ru'))->toBe('Урок 1 08.15 – 09.00')
        ->and(ContentTranslator::scheduleCell('Lecția 3 10.10 – 10.55', 'en'))->toBe('Lesson 3 10.10 – 10.55')
        // Cheia exactă din content (zilele) merge în continuare.
        ->and(ContentTranslator::scheduleCell('Luni', 'ru'))->toBe('Понедельник')
        // Diacriticele LEGACY cu sedilă (ş/ţ) din datele migrate se potrivesc cu cheile standard.
        ->and(ContentTranslator::scheduleCell("Educa\u{0163}ie fizic\u{0103} Ciobanu A.", 'ru'))->toBe('Физкультура Ciobanu A.')
        // Zilele săptămânii ORIUNDE în celulă (orarele de examene).
        ->and(ContentTranslator::scheduleCell('Luni 04.05.2026', 'ru'))->toBe('Понедельник 04.05.2026')
        // Fără potrivire → RO neatins; RO → identitate.
        ->and(ContentTranslator::scheduleCell('Ședință administrativă, cab. 5', 'ru'))->toBe('Ședință administrativă, cab. 5')
        ->and(ContentTranslator::scheduleCell('Matematică Damian Iu.', 'ro'))->toBe('Matematică Damian Iu.');
});

it('Post.localized* întoarce traducerea limbii curente, cu fallback RO', function () {
    $post = Post::factory()->create([
        'title' => 'Titlu RO',
        'excerpt' => 'Rezumat RO',
        'content' => '<p>Conținut RO</p>',
        'category' => 'actualitati',
        'published_at' => now(),
    ]);
    PostTranslation::factory()->for($post)->create([
        'locale' => 'ru',
        'title' => 'Заголовок',
        'excerpt' => 'Аннотация',
        'content' => '<p>Текст</p>',
    ]);
    $post->load('translations');

    app()->setLocale('ru');
    expect($post->localizedTitle())->toBe('Заголовок')
        ->and($post->localizedExcerpt())->toBe('Аннотация')
        ->and($post->localizedContent())->toBe('<p>Текст</p>');

    app()->setLocale('en');
    expect($post->localizedTitle())->toBe('Titlu RO');

    app()->setLocale('ro');
    expect($post->localizedTitle())->toBe('Titlu RO');
});

it('Post cade pe conținutul RO când rândul de traducere are content null', function () {
    $post = Post::factory()->create([
        'title' => 'T',
        'content' => '<p>RO</p>',
        'category' => 'blog',
        'published_at' => now(),
    ]);
    PostTranslation::factory()->for($post)->create([
        'locale' => 'ru',
        'title' => 'РУ',
        'content' => null,
    ]);
    $post->load('translations');

    app()->setLocale('ru');
    expect($post->localizedTitle())->toBe('РУ')
        ->and($post->localizedContent())->toBe('<p>RO</p>');
});

it('servește pagina publică cu conținut tradus sub /ru', function () {
    $roLead = 'Curriculumul la disciplină pentru treapta gimnazială (clasele V–IX).';

    $this->get('/ru/gimnazicheskaya-shkola/kurrikulum')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/page')
            ->where('locale', 'ru')
            ->where('sections.0.type', 'lead')
            ->where('sections.0.text', fn (string $text): bool => $text !== $roLead && $text !== ''));
});
