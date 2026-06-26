<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\TeacherDirectory;
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

        // Conducerea (primii 5 din grupul „Administrație"), pentru secțiunea Personal de pe homepage.
        $leadership = collect(TeacherDirectory::groups())
            ->first()['members'] ?? [];

        return Inertia::render('public/home', [
            'latestNews' => $latestNews,
            'leadership' => array_slice($leadership, 0, 5),
        ]);
    }
}
