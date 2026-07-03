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

        // Personalul pentru secțiunea „Echipa" de pe homepage: Daniță Ghenadie (mereu primul,
        // pinned) + TOT restul personalului liceului, din toate grupurile de pe /personal
        // (administrație, învățători, profesori, activități extrașcolare) — 3 sloturi rotesc
        // live pe frontend, aleatoriu, fără repetare până nu a fost afișată toată echipa.
        // Vezi `leadership-grid.tsx`.
        $leadership = collect(TeacherDirectory::groups())
            ->flatMap(fn (array $group): array => $group['members'])
            ->values()
            ->all();

        return Inertia::render('public/home', [
            'latestNews' => $latestNews,
            'leadership' => $leadership,
        ]);
    }
}
