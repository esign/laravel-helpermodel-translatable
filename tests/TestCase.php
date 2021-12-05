<?php

namespace Esign\HelperModelTranslatable\Tests;

use Esign\HelperModelTranslatable\Tests\Models\Post;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRoutes();
    }

    protected function getEnvironmentSetUp($app)
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('body')->nullable();
        });
        Schema::create('post_translations', function (Blueprint $table) {
            $table->id();
            $table->string('language', 5);
            $table->foreignId('post_id')->constrained('posts');
            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->string('field_with_accessor')->nullable();
        });
    }

    protected function setUpRoutes(): void
    {
        Route::get('/{post}', function (Post $post) {
            return $post->getTranslation('title');
        })->middleware(SubstituteBindings::class);
    }
}