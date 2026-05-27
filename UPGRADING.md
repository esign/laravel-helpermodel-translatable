# Upgrading

## Unreleased

### Renamed `language` column to `locale`

The `language` column on your translation tables should be renamed to `locale`. You can create a migration to handle this:

```php
Schema::table('post_translations', function (Blueprint $table) {
    $table->renameColumn('language', 'locale');
});
```

### Database-defined fallback translations

Fallback translations can now be defined per-row on your translation tables using a `fallback_locale` column, instead of relying solely on the application's `fallback_locale` config value.

Add the `fallback_locale` column to your translation tables:

```php
Schema::table('post_translations', function (Blueprint $table) {
    $table->string('fallback_locale', 5)->nullable()->after('locale');
});
```

The `getFallbackLocale()` method now reads from the translation model's `fallback_locale` column first. If not defined, it falls back to `config('app.fallback_locale')`.

Example data:
```json
{"id": 1, "post_id": 1, "locale": "nl-be", "fallback_locale": null, "title": "Home"}
{"id": 2, "post_id": 1, "locale": "nl-nl", "fallback_locale": "nl-be", "title": null}
```

The main locale does not define a `fallback_locale`. Only secondary locales that need to fall back to another locale should have this column set.

### New `whereFallbackTranslation` / `orWhereFallbackTranslation` scopes

Two new query scopes are available for querying models via their database-defined fallback:

```php
Post::whereFallbackTranslation('title', '<>', '');
Post::whereTranslation('title', '=', $value, App::getLocale())
    ->orWhereFallbackTranslation('title', '=', $value);
```

### `getTranslationModel` now supports fallback

The `getTranslationModel` method now accepts a second `useFallbackLocale` parameter, consistent with `getTranslation`:

```php
$post->getTranslationModel('nl');        // returns null if no model exists
$post->getTranslationModel('nl', true);  // falls back to the fallback locale's model
```

### Route model binding with fallback support

The `resolveRouteBinding` method now automatically resolves models via the fallback translation when no match is found for the current locale. This requires the `fallback_locale` column to be present on your translation tables.
