<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('title');
            $table->json('excerpt')->nullable();
            $table->json('body')->nullable();
            $table->string('status')->default('draft');
            $table->string('type')->default('text');
            $table->string('video_url')->nullable();
            $table->foreignId('user_id')->constrained(config('auth.providers.users.table', 'users'))->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('blog_categories')->restrictOnDelete();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->string('last_viewer_ip')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->string('meta_og_image')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at']);
            $table->index('scheduled_for');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
