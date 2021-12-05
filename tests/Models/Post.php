<?php

namespace Esign\HelperModelTranslatable\Tests\Models;

use Esign\HelperModelTranslatable\HelperModelTranslatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use HelperModelTranslatable;

    protected $guarded = [];
    public $timestamps = false;
    public $translatable = [
        'title',
        'slug',
        'field_with_accessor',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function secondaryTranslations(): HasMany
    {
        return $this->hasMany(PostTranslation::class);
    }
}
