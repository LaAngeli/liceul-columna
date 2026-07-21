<?php

namespace App\Filament\Content\Support;

use App\Actions\Cms\SanitizeHtml;
use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Content\Concerns\HandlesPublishDate;
use App\Filament\Content\Concerns\ManagesArticleTranslations;
use App\Models\Post;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Pagină de bază pentru editarea unui articol. Hidratează taburile RU/EN din `post_translations`
 * la deschidere și le persistă la salvare, sanitizând conținutul.
 */
abstract class BaseEditArticle extends EditRecord
{
    use HandlesPublishDate;
    use ManagesArticleTranslations;
    use PlacesRecordActionsWithForm;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Post $record */
        $record = $this->record;

        foreach (['ru', 'en'] as $locale) {
            $translation = $record->translations->firstWhere('locale', $locale);

            $data['translations'][$locale] = [
                'title' => $translation?->title,
                'slug' => $translation?->slug,
                'excerpt' => $translation?->excerpt,
                'content' => $translation?->content,
            ];
        }

        return $this->seedPublishDateToggle($data, $record->published_at);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Post $record */
        [$data, $translations] = $this->splitTranslations($data);

        $data = $this->resolvePublishDateOnUpdate($data);
        $data['content'] = app(SanitizeHtml::class)->handle($data['content'] ?? null);

        $record->update($data);

        $this->syncTranslations($record, $translations);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
