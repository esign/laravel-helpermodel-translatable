<?php

namespace Esign\HelperModelTranslatable\Tests;

use BadMethodCallException;
use Esign\HelperModelTranslatable\Exceptions\InvalidConfiguration;
use Esign\HelperModelTranslatable\Tests\Models\ConfiguredPost;
use Esign\HelperModelTranslatable\Tests\Models\Post;
use Esign\HelperModelTranslatable\Tests\Models\PostTranslation;
use Esign\HelperModelTranslatable\Tests\Models\PostWithFallback;
use Esign\HelperModelTranslatable\Tests\Models\SubNamespace\PostTranslation as SubNamespacePostTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

class HelperModelTranslatableTest extends TestCase
{
    use RefreshDatabase;

    protected function createPostTranslation(
        Model $post,
        string $language,
        array $attributes = []
    ): PostTranslation {
        return PostTranslation::create(array_merge(
            ['post_id' => $post->id, 'language' => $language],
            $attributes,
        ));
    }

    /** @test */
    public function it_can_check_if_an_attribute_is_translatable()
    {
        $post = Post::create();
        $this->assertTrue($post->isTranslatableAttribute('title'));
        $this->assertFalse($post->isTranslatableAttribute('non-translatable-field'));
    }

    /** @test */
    public function it_can_get_translatable_attributes()
    {
        $post = Post::create();
        $this->assertContains('title', $post->getTranslatableAttributes());
    }

    /** @test */
    public function it_can_throw_an_exception_if_the_helper_model_could_not_be_guessed()
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage("Failed to find helper model `PostTranslation` in namespaces [Esign\\HelperModelTranslatable\\NonExistingNamespace]");

        Config::set('helpermodel-translatable.model_namespaces', [
            'Esign\\HelperModelTranslatable\\NonExistingNamespace',
        ]);

        $post = Post::create();
        $post->getTranslation('title');
    }

    /** @test */
    public function it_can_check_if_it_has_a_translation()
    {
        $post = Post::create();
        $this->createPostTranslation($post, 'en', ['title' => 'Test en']);
        $this->createPostTranslation($post, 'nl', ['title' => null]);
        $this->createPostTranslation($post, 'fr', ['title' => '']);
        $this->createPostTranslation($post, 'de', ['tags' => ['🍎', '🍐', '🍋']]);
        $this->createPostTranslation($post, 'es', ['tags' => []]);

        $this->assertTrue($post->hasTranslation('title'));
        $this->assertTrue($post->hasTranslation('title', 'en'));
        $this->assertFalse($post->hasTranslation('title', 'nl'));
        $this->assertFalse($post->hasTranslation('title', 'fr'));
        $this->assertTrue($post->hasTranslation('tags', 'de'));
        $this->assertFalse($post->hasTranslation('tags', 'es'));
    }

    /** @test */
    public function it_can_check_if_it_has_a_translation_model()
    {
        $post = Post::create();
        $this->createPostTranslation($post, 'en');

        $this->assertTrue($post->hasTranslationModel());
        $this->assertTrue($post->hasTranslationModel('en'));
        $this->assertFalse($post->hasTranslationModel('nl'));
    }

    /** @test */
    public function it_can_define_a_custom_helper_model()
    {
        $post = ConfiguredPost::create();
        $this->createPostTranslation($post, 'en');

        $this->assertInstanceOf(PostTranslation::class, $post->translations->first());
    }

    /** @test */
    public function it_can_configure_a_custom_namespace()
    {
        $post = Post::create();
        $this->createPostTranslation($post, 'en');

        Config::set('helpermodel-translatable.model_namespaces', [
            'Esign\\HelperModelTranslatable\\Tests\\Models\\SubNamespace',
        ]);

        $this->assertInstanceOf(SubNamespacePostTranslation::class, $post->translations->first());
    }

    /** @test */
    public function it_can_configure_a_custom_namespace_using_a_string()
    {
        $post = Post::create();
        $this->createPostTranslation($post, 'en');

        Config::set('helpermodel-translatable.model_namespaces', 'Esign\\HelperModelTranslatable\\Tests\\Models\\SubNamespace');

        $this->assertInstanceOf(SubNamespacePostTranslation::class, $post->translations->first());
    }

    /** @test */
    public function it_can_get_the_translations_relationship()
    {
        $post = Post::create();
        $this->assertInstanceOf(HasMany::class, $post->translations());
    }

    /** @test */
    public function it_wont_interfere_when_getting_non_translatable_attributes()
    {
        $post = Post::create(['body' => 'Test']);
        $this->assertEquals('Test', $post->body);
    }

    /** @test */
    public function it_can_get_a_translation()
    {
        $post = Post::create();
        $this->createPostTranslation($post, 'en', ['title' => 'Test en']);
        $this->createPostTranslation($post, 'nl', ['title' => 'Test nl']);

        $this->assertEquals('Test en', $post->getTranslation('title'));
        $this->assertEquals('Test nl', $post->getTranslation('title', 'nl'));
        $this->assertEquals('Test en', $post->getTranslation('title', 'en'));
    }

    /** @test */
    public function it_can_get_the_translation_model()
    {
        $post = Post::create();
        $postTranslationEn = $this->createPostTranslation($post, 'en', ['title' => 'Test en']);
        $postTranslationNl = $this->createPostTranslation($post, 'nl', ['title' => 'Test nl']);

        $this->assertTrue($post->getTranslationModel()->is($postTranslationEn));
        $this->assertTrue($post->getTranslationModel('nl')->is($postTranslationNl));
        $this->assertNull($post->getTranslationModel('fr'));
    }

    /** @test */
    public function it_can_get_a_translation_using_a_fallback()
    {
        $post = Post::create();
        $this->createPostTranslation($post, 'en', ['title' => 'Test en']);
        $this->createPostTranslation($post, 'nl', ['title' => 'Test nl']);

        $this->assertEquals('Test nl', $post->getTranslation('title', 'nl', true));
        $this->assertEquals('Test en', $post->getTranslation('title', 'en', true));
        $this->assertEquals('Test en', $post->getTranslation('title', 'fr', true));
    }

    /** @test */
    public function it_can_get_a_translation_without_using_a_fallback()
    {
        $post = Post::create();
        $this->createPostTranslation($post, 'en', ['title' => 'Test en']);
        $this->createPostTranslation($post, 'nl', ['title' => 'Test nl']);

        $this->assertEquals('Test nl', $post->getTranslation('title', 'nl', false));
        $this->assertEquals('Test en', $post->getTranslation('title', 'en', false));
        $this->assertNull($post->getTranslation('title', 'fr', false));
    }

    /** @test */
    public function it_can_get_a_translation_with_a_fallback()
    {
        $post = Post::create();
        $this->createPostTranslation($post, 'en', ['title' => 'Test en']);
        $this->createPostTranslation($post, 'nl', ['title' => 'Test nl']);

        $this->assertEquals('Test en', $post->getTranslationWithFallback('title'));
        $this->assertEquals('Test nl', $post->getTranslationWithFallback('title', 'nl'));
        $this->assertEquals('Test en', $post->getTranslationWithFallback('title', 'en'));
        $this->assertEquals('Test en', $post->getTranslationWithFallback('title', 'fr'));
    }

    /** @test */
    public function it_can_get_a_translation_without_a_fallback()
    {
        $post = Post::create();
        $this->createPostTranslation($post, 'en', ['title' => 'Test en']);
        $this->createPostTranslation($post, 'nl', ['title' => 'Test nl']);

        $this->assertEquals('Test en', $post->getTranslationWithoutFallback('title'));
        $this->assertEquals('Test nl', $post->getTranslationWithoutFallback('title', 'nl'));
        $this->assertEquals('Test en', $post->getTranslationWithoutFallback('title', 'en'));
        $this->assertNull($post->getTranslationWithoutFallback('title', 'fr'));
    }

    /** @test */
    public function it_can_get_a_translation_with_a_configured_fallback()
    {
        $post = PostWithFallback::create();
        $this->createPostTranslation($post, 'en', ['title' => 'Test en']);
        $this->createPostTranslation($post, 'fr', ['title' => 'Test fr']);

        $this->assertEquals('Test en', $post->getTranslationWithFallback('title'));
        $this->assertEquals('Test en', $post->getTranslationWithFallback('title', 'en'));
        $this->assertEquals('Test fr', $post->getTranslationWithFallback('title', 'nl'));
        $this->assertEquals('Test fr', $post->getTranslationWithFallback('title', 'fr'));
    }

    /** @test */
    public function it_can_get_a_translation_using_a_property()
    {
        $post = Post::create();
        $this->createPostTranslation($post, 'en', ['title' => 'Test en']);
        $this->createPostTranslation($post, 'nl', ['title' => 'Test nl']);

        App::setLocale('nl');
        $this->assertEquals('Test nl', $post->title);

        App::setLocale('en');
        $this->assertEquals('Test en', $post->title);

        App::setLocale('fr');
        $this->assertNull($post->title);
    }

    /** @test */
    public function it_can_get_a_translation_using_an_accessor()
    {
        $post = Post::create();
        $this->createPostTranslation($post, 'en', ['field_with_accessor' => 'Test en']);

        $this->assertEquals('test en', $post->field_with_accessor);
    }

    /** @test */
    public function it_can_resolve_route_binding_for_translated_attributes()
    {
        $post = Post::create();
        $this->createPostTranslation($post, 'nl', ['title' => 'Post nl', 'slug' => 'post-nl']);
        $this->createPostTranslation($post, 'en', ['title' => 'Post en', 'slug' => 'post-en']);

        App::setLocale('nl');
        $this->get('/post-nl')->assertSee('Post nl');

        App::setLocale('en');
        $this->get('/post-en')->assertSee('Post en');
    }

    /** @test */
    public function it_can_resolve_route_binding_for_the_active_language_if_they_have_matching_slugs()
    {
        $postA = Post::create();
        $postB = Post::create();
        $this->createPostTranslation($postA, 'nl', ['title' => 'Post nl', 'slug' => 'post']);
        $this->createPostTranslation($postB, 'en', ['title' => 'Post en', 'slug' => 'post']);

        App::setLocale('nl');
        $this->get('/post')->assertSee('Post nl');

        App::setLocale('en');
        $this->get('/post')->assertSee('Post en');
    }

    /** @test */
    public function it_can_use_another_relationship()
    {
        $post = Post::create();
        $this->createPostTranslation($post, 'en', ['title' => 'Post en']);

        $this->assertEquals(
            'Post en',
            $post->useHelperModelRelation('secondaryTranslations')->getTranslation('title', 'en')
        );
    }

    /** @test */
    public function it_can_use_the_default_relationship()
    {
        $post = Post::create();
        $this->createPostTranslation($post, 'en', ['title' => 'Post en']);

        $this->assertEquals(
            'Post en',
            $post->useDefaultHelperModelRelation()->getTranslation('title', 'en')
        );
    }

    /** @test */
    public function it_can_use_the_where_translation_scope()
    {
        $postA = Post::create();
        $postB = Post::create();
        $this->createPostTranslation($postA, 'en', ['title' => 'Post en']);
        $this->createPostTranslation($postB, 'nl', ['title' => 'Post nl']);

        $posts = Post::whereTranslation('title', '=', 'Post en')->get();

        $this->assertTrue($posts->contains($postA));
        $this->assertFalse($posts->contains($postB));
    }

    /** @test */
    public function it_can_use_the_where_translation_scope_while_mixing_the_operator_and_value()
    {
        $postA = Post::create();
        $postB = Post::create();
        $this->createPostTranslation($postA, 'en', ['title' => 'Post en']);
        $this->createPostTranslation($postB, 'nl', ['title' => 'Post nl']);

        $posts = Post::whereTranslation('title', 'Post en')->get();

        $this->assertTrue($posts->contains($postA));
        $this->assertFalse($posts->contains($postB));
    }

    /** @test */
    public function it_can_use_the_or_where_translation_scope()
    {
        $postA = Post::create();
        $postB = Post::create();
        $this->createPostTranslation($postA, 'en', ['title' => 'Post en']);
        $this->createPostTranslation($postB, 'nl', ['title' => 'Post nl']);

        $posts = Post::whereTranslation('title', '=', 'Post en')->orWhereTranslation('title', '=', 'Post nl')->get();

        $this->assertTrue($posts->contains($postA));
        $this->assertTrue($posts->contains($postB));
    }

    /** @test */
    public function it_can_use_the_where_translation_scope_without_a_given_locale()
    {
        $postA = Post::create();
        $postB = Post::create();
        $this->createPostTranslation($postA, 'en', ['title' => 'Post']);
        $this->createPostTranslation($postB, 'nl', ['title' => 'Post']);

        $posts = Post::whereTranslation('title', '=', 'Post')->get();

        $this->assertTrue($posts->contains($postA));
        $this->assertTrue($posts->contains($postB));
    }

    /** @test */
    public function it_can_use_the_where_translation_scope_using_a_string_as_a_locale()
    {
        $postA = Post::create();
        $postB = Post::create();
        $this->createPostTranslation($postA, 'en', ['title' => 'Post']);
        $this->createPostTranslation($postB, 'nl', ['title' => 'Post']);

        $posts = Post::whereTranslation('title', '=', 'Post', 'en')->get();

        $this->assertTrue($posts->contains($postA));
        $this->assertFalse($posts->contains($postB));
    }

    /** @test */
    public function it_can_use_the_where_translation_scope_using_an_array_as_a_locale()
    {
        $postA = Post::create();
        $postB = Post::create();
        $this->createPostTranslation($postA, 'en', ['title' => 'Post']);
        $this->createPostTranslation($postB, 'nl', ['title' => 'Post']);

        $posts = Post::whereTranslation('title', '=', 'Post', ['en'])->get();

        $this->assertTrue($posts->contains($postA));
        $this->assertFalse($posts->contains($postB));
    }

    /** @test */
    public function it_can_use_the_translated_in_scope_using_a_string()
    {
        $post = Post::create();
        $this->createPostTranslation($post, 'nl', ['title' => 'Post nl']);

        $postsNl = Post::translatedIn('nl')->get();
        $postsFr = Post::translatedIn('fr')->get();

        $this->assertTrue($postsNl->contains($post));
        $this->assertFalse($postsFr->contains($post));
    }

    /** @test */
    public function it_can_use_the_translated_in_scope_using_an_array()
    {
        $postA = Post::create();
        $postB = Post::create();
        $this->createPostTranslation($postA, 'nl', ['title' => 'Post nl']);
        $this->createPostTranslation($postB, 'en', ['title' => 'Post en']);

        $posts = Post::translatedIn(['nl', 'fr'])->get();

        $this->assertTrue($posts->contains($postA));
        $this->assertFalse($posts->contains($postB));
    }

    /** @test */
    public function it_cannot_use_the_internal_locale_scope()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('/scopeLocale()/');

        Post::locale('nl')->get();
    }

    /** @test */
    public function it_can_use_the_or_translated_in_scope()
    {
        $postA = Post::create();
        $postB = Post::create();
        $this->createPostTranslation($postA, 'en', ['title' => 'Post en']);
        $this->createPostTranslation($postB, 'nl', ['title' => 'Post nl']);

        $posts = Post::translatedIn('nl')->orTranslatedIn('en')->get();

        $this->assertTrue($posts->contains($postA));
        $this->assertTrue($posts->contains($postB));
    }
}
