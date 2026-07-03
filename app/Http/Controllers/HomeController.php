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
            // `published_at` nu are componentă de oră (mereu miezul nopții) — `id` desparte
            // articolele publicate în aceeași zi, păstrând ordinea reală de creare.
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get()
            ->map(fn (Post $post): array => [
                'title' => $post->localizedTitle(),
                'slug' => $post->slug,
                'excerpt' => $post->localizedExcerpt(),
                'image' => $post->imageUrl(),
                'date' => $post->published_at?->translatedFormat('d F Y'),
            ]);

        // Conducerea pentru secțiunea „Echipa" de pe homepage: Daniță Ghenadie (mereu primul) +
        // un bazin de membri din grupul „Administrație" (primii 6, fără bucătarul-șef) din care
        // 3 sloturi rotesc live pe frontend. Vezi `leadership-grid.tsx`.
        $leadership = collect(TeacherDirectory::groups())
            ->first()['members'] ?? [];

        return Inertia::render('public/home', [
            'latestNews' => $latestNews,
            'leadership' => array_slice($leadership, 0, 6),
        ]);
    }
}
