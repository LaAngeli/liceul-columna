<?php

namespace App\Actions\Cms;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitizare HTML la salvare (aprobat de user) — apărare în adâncime peste RichEditor: chiar dacă
 * un actor de încredere lipește ceva periculos sau editorul e ocolit, corpul stocat rămâne curat.
 * Whitelist-ul de taguri = exact ce produce toolbarul restrâns al RichEditor-ului.
 */
class SanitizeHtml
{
    public function handle(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,em,u,s,a[href|title],h2,h3,ul,ol,li,blockquote');
        $config->set('AutoFormat.RemoveEmpty', true);
        // Link-urile externe primesc rel="noopener noreferrer" + target _blank (protecție tabnabbing).
        $config->set('HTML.TargetBlank', true);
        // Fără cache pe disc (evită dependența de un director scriibil); volumul de conținut e mic.
        $config->set('Cache.DefinitionImpl', null);

        return (new HTMLPurifier($config))->purify($html);
    }
}
