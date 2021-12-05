<?php

namespace Esign\HelperModelTranslatable\Tests\Models\SubNamespace;

use Illuminate\Database\Eloquent\Model;

class PostTranslation extends Model
{
    protected $guarded = [];
    public $timestamps = false;

    public function getFieldWithAccessorAttribute($value)
    {
        return strtolower($value);
    }
}
