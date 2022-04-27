<?php

namespace Esign\HelperModelTranslatable\Tests\Models;

use Esign\HelperModelTranslatable\HelperModelTranslatable;
use Illuminate\Database\Eloquent\Model;

class PostWithFallback extends Model
{
    use HelperModelTranslatable;

    protected $guarded = [];
    protected $table = 'posts';
    public $timestamps = false;
    public $translatable = ['title'];

    public function getFallbackLocale(?string $locale = null): ?string
    {
        return 'fr';
    }

    protected function getHelperModelClass(): string
    {
        return PostTranslation::class;
    }

    protected function getHelperModelForeignKey(): string
    {
        return 'post_id';
    }
}
