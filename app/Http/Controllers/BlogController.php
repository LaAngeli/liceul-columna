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

    public function index(string $category): Response
    {
        $posts = Post::query()
            ->published()
            ->category($category)
            ->with('translations')
            ->orderByDesc('published_at')
            ->paginate(9)
            ->through(fn (Post $post): array => [
                'title' => $post->localizedTitle(),
                'slug' => $post->slug,
                'excerpt' => $post->localizedExcerpt(),
                'image' => $post->image,
                'date' => $post->published_at?->translatedFormat('d F Y'),
            ]);

        return Inertia::render('public/articole/index', [
            'pageTitle' => self::TITLES[$category] ?? 'Articole',
            'category' => $category,
            'posts' => $posts,
        ]);
    }

    public function show(Post $post): Response
    {
        abort_if($post->published_at === null, 404);

        return Inertia::render('public/articole/show', [
            'post' => [
                'title' => $post->localizedTitle(),
                'category' => $post->category,
                'categoryLabel' => self::TITLES[$post->category] ?? 'Articole',
                'categoryUrl' => $post->category === 'blog' ? '/blog' : '/actualitati-si-evenimente',
                'image' => $post->image,
                'content' => $post->localizedContent(),
                'date' => $post->published_at->translatedFormat('d F Y'),
            ],
        ]);
    }
}
