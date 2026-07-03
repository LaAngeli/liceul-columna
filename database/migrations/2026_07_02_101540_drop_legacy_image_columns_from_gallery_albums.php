<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restructurare a galeriei: imaginile trec din array-ul JSON `gallery_albums.images` în tabelul
 * dedicat `gallery_images`. Descrierea de album (`description`) e eliminată — nu era folosită pe
 * site și nu mai apare în formular. Migrarea MUTĂ datele existente înainte de a șterge coloanele,
 * ca dev-ul să nu piardă albumele deja importate.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('gallery_albums')->select('id', 'images')->get() as $album) {
            $images = json_decode((string) ($album->images ?? '[]'), true);

            if (! is_array($images)) {
                continue;
            }

            $order = 0;
            foreach (array_values($images) as $path) {
                if (! is_string($path) || $path === '') {
                    continue;
                }

                $order++;
                DB::table('gallery_images')->insert([
                    'gallery_album_id' => $album->id,
                    'path' => $path,
                    'sort_order' => $order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('gallery_albums', function (Blueprint $table) {
            $table->dropColumn(['images', 'description']);
        });
    }

    public function down(): void
    {
        Schema::table('gallery_albums', function (Blueprint $table) {
            $table->json('images')->nullable();
            $table->text('description')->nullable();
        });

        $grouped = DB::table('gallery_images')->orderBy('sort_order')->get()->groupBy('gallery_album_id');

        foreach ($grouped as $albumId => $rows) {
            DB::table('gallery_albums')->where('id', $albumId)->update([
                'images' => json_encode($rows->pluck('path')->all()),
            ]);
        }
    }
};
