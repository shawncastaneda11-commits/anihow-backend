<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crop_care_articles', function (Blueprint $table) {
            $table->id();

            // Articles are farm-scoped. There are no system-authored articles.
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('title');
            $table->string('slug');
            $table->string('excerpt')->nullable();
            $table->text('body');
            $table->string('image_path')->nullable();

            // App\Enums\ArticleCategory
            $table->string('category');

            // App\Enums\ArticleStatus. Content editors need a draft state.
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Two farms may both publish "Pest Management for Kamatis", so the
            // slug is unique per farm and the farm slug goes in the URL path.
            $table->unique(['farm_id', 'slug']);
            $table->index(['farm_id', 'status']);
        });

        // Articles tag to the shared taxonomy even though the article itself
        // belongs to one farm. One article may cover several crops.
        Schema::create('article_crop_type', function (Blueprint $table) {
            $table->foreignId('crop_care_article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crop_type_id')->constrained()->cascadeOnDelete();

            $table->primary(['crop_care_article_id', 'crop_type_id'], 'article_crop_type_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_crop_type');
        Schema::dropIfExists('crop_care_articles');
    }
};
