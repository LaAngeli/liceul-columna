<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    private const TITLES = [
        'actualitati' => 'Actualități/Evenimente',
        'blog' => 'Blog',
    ];

    /**
     * Lista de articole a unei categorii. Trimitem TOATE articolele (titlu/rezumat/imagine/dată/an)
     * ca frontend-ul să poată căuta live, filtra pe an și încărca treptat — fără reîncărcări de pagină.
     */
    public function index(string $category): Response
    {
        $posts = Post::query()
            ->published()
            ->category($category)
            ->with('translations')
            // `published_at` nu are componentă de oră (mereu miezul nopții) — `id` desparte
            // articolele publicate în aceeași zi, păstrând ordinea reală de creare.
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Post $post): array => [
                'title' => $post->localizedTitle(),
                'slug' => $post->slug,
                'excerpt' => $post->localizedExcerpt(),
                'image' => $post->imageUrl(),
                'date' => $post->published_at?->translatedFormat('d F Y'),
                'year' => (int) $post->published_at?->format('Y'),
            ]);

        return Inertia::render('public/articole/index', [
            'pageTitle' => self::TITLES[$category] ?? 'Articole',
            'category' => $category,
            'posts' => $posts->values()->all(),
        ]);
    }

    public function show(Post $post): Response
    {
        abort_if($post->published_at === null, 404);

        $content = $post->localizedContent();
        $words = str_word_count(strip_tags($content));

        $related = Post::query()
            ->published()
            ->category($post->category)
            ->whereKeyNot($post->getKey())
            ->with('translations')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get()
            ->map(fn (Post $related): array => [
                'title' => $related->localizedTitle(),
                'slug' => $related->slug,
                'image' => $related->imageUrl(),
                'date' => $related->published_at?->translatedFormat('d F Y'),
            ]);

        return Inertia::render('public/articole/show', [
            'post' => [
                'title' => $post->localizedTitle(),
                'category' => $post->category,
                'categoryLabel' => self::TITLES[$post->category] ?? 'Articole',
                'categoryUrl' => $post->category === 'blog' ? '/blog' : '/actualitati-si-evenimente',
                'image' => $post->imageUrl(),
                'content' => $content,
                'date' => $post->published_at->translatedFormat('d F Y'),
                'readingMinutes' => max(1, (int) ceil($words / 200)),
            ],
            'related' => $related->values()->all(),
        ]);
    }
}
