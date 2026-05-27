<?php

namespace Esign\HelperModelTranslatable;

use Closure;
use Esign\HelperModelTranslatable\Exceptions\InvalidConfiguration;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

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

    public function getTranslationModel(?string $locale = null, bool $useFallbackLocale = false): ?Model
    {
        $model = $this->{$this->helperModelRelation}->firstWhere('locale', $locale ?? App::getLocale());

        if (is_null($model) && $useFallbackLocale) {
            $model = $this->{$this->helperModelRelation}->firstWhere('locale', $this->getFallbackLocale($locale));
        }

        return $model;
    }

    public function hasTranslationModel(?string $locale = null): bool
    {
        return (bool) $this->getTranslationModel($locale);
    }

    public function getFallbackLocale(?string $locale = null): ?string
    {
        $locale ??= App::getLocale();
        $translationModel = $this->getTranslationModel($locale);

        if ($translationModel && ! blank($translationModel->fallback_locale)) {
            return $translationModel->fallback_locale;
        }

        return config('app.fallback_locale');
    }

    public function getTranslation(string $key, ?string $locale = null, bool $useFallbackLocale = false): mixed
    {
        $translation = $this->getTranslationModel($locale);

        $value = $translation?->{$key};

        if (blank($value) && $useFallbackLocale) {
            $value = $this->getTranslation($key, $this->getFallbackLocale($locale), false);
        }

        return $value;
    }

    public function hasTranslation(string $key, ?string $locale = null): bool
    {
        return ! blank($this->getTranslationWithoutFallback($key, $locale));
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
        mixed $value = null,
        string | array | null $locale = null,
    ): Builder {
        return $query->whereHas($this->helperModelRelation, function (Builder $query) use ($column, $operator, $value, $locale) {
            $query->where($column, $operator, $value);

            if (! is_null($locale)) {
                $this->scopeLocale($query, $locale);
            }
        });
    }

    public function scopeOrWhereTranslation(
        Builder $query,
        Closure | string | array | Expression $column,
        mixed $operator = null,
        mixed $value = null,
        string | array | null $locale = null,
    ): Builder {
        return $query->orWhereHas($this->helperModelRelation, function (Builder $query) use ($column, $operator, $value, $locale) {
            $query->where($column, $operator, $value);

            if (! is_null($locale)) {
                $this->scopeLocale($query, $locale);
            }
        });
    }

    public function scopeTranslatedIn(Builder $query, string | array $locale): Builder
    {
        return $this->scopeWhereTranslation($query, function (Builder $query) use ($locale) {
            return $this->scopeLocale($query, $locale);
        });
    }

    public function scopeOrTranslatedIn(Builder $query, string | array $locale): Builder
    {
        return $this->scopeOrWhereTranslation($query, function (Builder $query) use ($locale) {
            return $this->scopeLocale($query, $locale);
        });
    }

    /**
     * This scope is for internal use only, hence why it's private.
     */
    private function scopeLocale(Builder $query, string | array $locale): Builder
    {
        return $query->when(
            is_array($locale),
            fn (Builder $query) => $query->whereIn('locale', $locale),
            fn (Builder $query) => $query->where('locale', $locale),
        );
    }

    public function scopeWhereFallbackTranslation(
        Builder $query,
        Closure | string | array | Expression $column,
        mixed $operator = null,
        mixed $value = null,
        string $boolean = 'and',
    ): Builder {
        $helperModelClass = $this->getHelperModelClass();
        $helperModelForeignKey = $this->getHelperModelForeignKey();
        /** @var \Illuminate\Database\Eloquent\Model $helperModel */
        $helperModel = new $helperModelClass;
        $helperTable = $helperModel->getTable();

        $whereExistsStatement = $boolean === 'and' ? 'whereExists' : 'orWhereExists';

        return $query->{$whereExistsStatement}(function (QueryBuilder $fallbackQuery) use ($helperTable, $helperModelForeignKey, $column, $operator, $value) {
            $fallbackQuery
                ->select(DB::raw(1))
                ->from("{$helperTable} as base")
                ->join("{$helperTable} as fallback", function (JoinClause $join) use ($helperModelForeignKey) {
                    $join
                        ->on('fallback.' . $helperModelForeignKey, '=', 'base.' . $helperModelForeignKey)
                        ->on('fallback.locale', '=', 'base.fallback_locale');
                })
                ->whereColumn('base.' . $helperModelForeignKey, $this->qualifyColumn('id'))
                ->where('base.locale', App::getLocale())
                ->where(is_string($column) ? "fallback.$column" : $column, $operator, $value);
        });
    }

    public function scopeOrWhereFallbackTranslation(
        Builder $query,
        Closure | string | array | Expression $column,
        mixed $operator = null,
        mixed $value = null,
    ): Builder {
        return $this->scopeWhereFallbackTranslation($query, $column, $operator, $value, 'or');
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();

        if ($this->isTranslatableAttribute($field)) {
            return $this
                ->whereTranslation($field, '=', $value, App::getLocale())
                ->orWhereFallbackTranslation($field, '=', $value)
                ->first();
        }

        return parent::resolveRouteBinding($value, $field);
    }
}
