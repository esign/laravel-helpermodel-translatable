<?php

namespace Esign\HelperModelTranslatable\Tests;

use Esign\HelperModelTranslatable\Exceptions\InvalidConfiguration;
use Esign\HelperModelTranslatable\Tests\Models\ConfiguredPost;
use Esign\HelperModelTranslatable\Tests\Models\Post;
use Esign\HelperModelTranslatable\Tests\Models\PostTranslation;
use Esign\HelperModelTranslatable\Tests\Models\SubNamespace\PostTranslation as SubNamespacePostTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

class HelperModelTranslatableTest extends TestCase
{
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

        $this->assertEquals('Test nl', $post->getTranslation('title', 'nl'));
        $this->assertEquals('Test en', $post->getTranslation('title', 'en'));
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

        $this->assertEquals('Test nl', $post->getTranslationWithoutFallback('title', 'nl'));
        $this->assertEquals('Test en', $post->getTranslationWithoutFallback('title', 'en'));
        $this->assertNull($post->getTranslationWithoutFallback('title', 'fr'));
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
}