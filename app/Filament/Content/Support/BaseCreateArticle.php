<?php

namespace App\Filament\Content\Support;

use App\Actions\Cms\SanitizeHtml;
use App\Enums\PostType;
use App\Filament\Content\Concerns\HandlesPublishDate;
use App\Filament\Content\Concerns\ManagesArticleTranslations;
use App\Models\Post;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Database\Eloquent\Model;

/**
 * Pagină de bază pentru crearea unui articol. Fixează categoria pe tipul resursei, sanitizează
 * conținutul RO și persistă traducerile RU/EN. Formularul e afișat ca WIZARD (Setări generale →
 * RO → RU → EN → Creare). Paginile concrete (Blog/Actualități) doar declară resursa + tipul.
 */
abstract class BaseCreateArticle extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;
    use HandlesPublishDate;
    use ManagesArticleTranslations;

    /**
     * Fără „Creați și creați altul": un articol e o piesă trilingvă amplă, iar fluxul obișnuit e
     * salvează → revizuiește/editează, nu pornirea imediată a unui articol gol.
     */
    protected static bool $canCreateAnother = false;

    abstract protected function postType(): PostType;

    /**
     * @return array<int, Step>
     */
    protected function getSteps(): array
    {
        return ArticleForm::wizardSteps();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        [$data, $translations] = $this->splitTranslations($data);

        $data = $this->resolvePublishDateOnCreate($data);
        $data['category'] = $this->postType()->value;
        $data['content'] = app(SanitizeHtml::class)->handle($data['content'] ?? null);

        /** @var Post $post */
        $post = static::getModel()::create($data);

        $this->syncTranslations($post, $translations);

        return $post;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
