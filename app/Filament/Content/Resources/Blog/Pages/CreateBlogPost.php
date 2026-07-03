<?php

namespace App\Filament\Content\Resources\Blog\Pages;

use App\Enums\PostType;
use App\Filament\Content\Resources\Blog\BlogResource;
use App\Filament\Content\Support\BaseCreateArticle;

class CreateBlogPost extends BaseCreateArticle
{
    protected static string $resource = BlogResource::class;

    protected function postType(): PostType
    {
        return PostType::Blog;
    }
}
