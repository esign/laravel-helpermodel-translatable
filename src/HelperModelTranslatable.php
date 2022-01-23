<?php

namespace Esign\HelperModelTranslatable;

use Closure;
use Esign\HelperModelTranslatable\Exceptions\InvalidConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\App;

trait HelperModelTranslatable
{
    protected $helperModelRelation = 'translations';

    public function isTranslatableAttribute(string $key): bool
    {
        return in_array($key, $this->getTranslatableAttributes());
    }

    public function getTranslatableAttributes(): array
    {
        return $this->translatable ?? [];
    }

    public function getTranslationModel(?string $locale = null): ?Model
    {
        return $this->{$this->helperModelRelation}->firstWhere('language', $locale ?? App::getLocale());
    }

    public function getTranslation(string $key, ?string $locale = null, bool $useFallbackLocale = false): mixed
    {
        $translation = $this->getTranslationModel($locale);

        $value = $translation?->{$key};

        if (empty($value) && $useFallbackLocale) {
            $value = $this->getTranslation($key, config('app.fallback_locale', $locale), false);
        }

        return $value;
    }

    public function hasTranslation(string $key, ?string $locale = null): bool
    {
        return ! empty($this->getTranslationWithoutFallback($key, $locale));
    }

    public function getTranslationWithFallback(string $key, ?string $locale = null): mixed
    {
        return $this->getTranslation($key, $locale, true);
    }

    public function getTranslationWithoutFallback(string $key, ?string $locale = null): mixed
    {
        return $this->getTranslation($key, $locale, false);
    }

    public function useHelperModelRelation(string $relation): self
    {
        $this->helperModelRelation = $relation;

        return $this;
    }

    public function useDefaultHelperModelRelation(): self
    {
        return $this->useHelperModelRelation('translations');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(
            $this->getHelperModelClass(),
            $this->getHelperModelForeignKey()
        );
    }

    public function getAttribute($key): mixed
    {
        if ($this->isTranslatableAttribute($key)) {
            return $this->getTranslation($key);
        }

        return parent::getAttribute($key);
    }

    protected function getHelperModelClass(): string
    {
        return $this->guessFullyQualifiedHelperModelClass();
    }

    protected function getHelperModelForeignKey(): string
    {
        return $this->guessHelperModelForeignKey();
    }

    protected function guessFullyQualifiedHelperModelClass(): string
    {
        $namespacesToTry = config('helpermodel-translatable.model_namespaces');
        $helperModelClass = $this->guessHelperModelClass();

        foreach ((array) $namespacesToTry as $namespace) {
            if (class_exists($className = $namespace . '\\' . $helperModelClass)) {
                return $className;
            }
        }

        throw InvalidConfiguration::helperModelNotFound($helperModelClass, $namespacesToTry);
    }

    protected function guessHelperModelClass(): string
    {
        return class_basename($this) . 'Translation';
    }

    protected function guessHelperModelForeignKey(): string
    {
        return $this->getForeignKey();
    }

    public function scopeWhereTranslation(
        Builder $query,
        Closure | string | array | Expression $column,
        mixed $operator = null,
        mixed $value = null
    ): Builder {
        return $query->whereHas($this->helperModelRelation, function (Builder $query) use ($column, $operator, $value) {
            $query->where($column, $operator, $value);
        });
    }

    public function scopeOrWhereTranslation(
        Builder $query,
        Closure | string | array | Expression $column,
        mixed $operator = null,
        mixed $value = null
    ): Builder {
        return $query->orWhereHas($this->helperModelRelation, function (Builder $query) use ($column, $operator, $value) {
            $query->where($column, $operator, $value);
        });
    }

    public function scopeTranslatedIn(Builder $query, string | array $locale): Builder
    {
        return $this->scopeWhereTranslation($query, function (Builder $query) use ($locale) {
            $query->when(
                is_array($locale),
                fn (Builder $query) => $query->whereIn('language', $locale),
                fn (Builder $query) => $query->where('language', $locale),
            );
        });
    }

    public function scopeOrTranslatedIn(Builder $query, string | array $locale): Builder
    {
        return $this->scopeOrWhereTranslation($query, function (Builder $query) use ($locale) {
            $query->when(
                is_array($locale),
                fn (Builder $query) => $query->whereIn('language', $locale),
                fn (Builder $query) => $query->where('language', $locale),
            );
        });
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();

        if ($this->isTranslatableAttribute($field)) {
            return $this
                ->whereTranslation($field, $value)
                ->translatedIn(App::getLocale())
                ->first();
        }

        return parent::resolveRouteBinding($value, $field);
    }
}
