<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        $latestNews = Post::query()
            ->published()
            ->category('actualitati')
            ->with('translations')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(fn (Post $post): array => [
                'title' => $post->localizedTitle(),
                'slug' => $post->slug,
                'excerpt' => $post->localizedExcerpt(),
                'image' => $post->image,
                'date' => $post->published_at?->translatedFormat('d F Y'),
            ]);

        return Inertia::render('public/home', [
            'latestNews' => $latestNews,
        ]);
    }
}
