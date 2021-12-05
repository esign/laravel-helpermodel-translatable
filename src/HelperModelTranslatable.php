<?php

namespace Esign\HelperModelTranslatable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

trait HelperModelTranslatable
{
    use RefreshDatabase;

    public function isTranslatableAttribute(string $key): bool
    {
        return in_array($key, $this->getTranslatableAttributes());
    }

    public function getTranslatableAttributes(): array
    {
        return $this->translatable ?? [];
    }

    public function getTranslation(string $key, ?string $locale = null, bool $useFallbackLocale = false): mixed
    {
        $translation =  $this->translations->firstWhere('language', $locale ?? App::getLocale());

        $value = $translation?->{$key};

        if (empty($value) && $useFallbackLocale) {
            $value = $this->getTranslation($key, config('app.fallback_locale', $locale), false);
        }

        return $value;
    }

    public function getTranslationWithFallback(string $key, ?string $locale): mixed
    {
        return $this->getTranslation($key, $locale, true);
    }

    public function getTranslationWithoutFallback(string $key, ?string $locale): mixed
    {
        return $this->getTranslation($key, $locale, false);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(
            $this->guessHelperModelClassName(),
            $this->guessHelperModelForeignKey()
        );
    }

    public function getAttribute($key): mixed
    {
        if ($this->isTranslatableAttribute($key)) {
            return $this->getTranslation($key);
        }

        return parent::getAttribute($key);
    }

    public function guessHelperModelClassName(): string
    {
        return get_class($this) . 'Translation';
    }

    public function guessHelperModelForeignKey(): string
    {
        return Str::snake(class_basename($this)) . '_id';
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();

        if ($this->isTranslatableAttribute($field)) {
            return $this->whereHas('translations', function (Builder $query) use ($field, $value) {
                $query->where($field, $value);
            })->first();
        }

        return parent::resolveRouteBinding($value, $field);
    }
}
