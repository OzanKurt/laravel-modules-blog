<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kurt\Modules\Blog\Http\Controllers\Api\CategoryController;
use Kurt\Modules\Blog\Http\Controllers\Api\CommentController;
use Kurt\Modules\Blog\Http\Controllers\Api\PostController;
use Kurt\Modules\Blog\Http\Controllers\Api\TagController;

/*
|--------------------------------------------------------------------------
| Blog REST API routes
|--------------------------------------------------------------------------
|
| This file is loaded by PackageServiceProvider::registerModuleApi() inside a
| Route::group() built from config/blog.php's `http` block (prefix `api/blog`,
| the `api` middleware, and the `throttle:blog-api` limiter). Read routes are
| declared plainly; write routes append the module's auth middleware per route
| (never nest another ApiRouteGroup, which would double the prefix/throttle).
|
*/

/** @var array<int, string> $auth */
$auth = config('blog.http.auth_middleware', ['auth']);

// Posts — reads (public, published scope respected).
Route::get('posts', [PostController::class, 'index'])->name('posts.index');
Route::get('posts/{post}/related', [PostController::class, 'related'])->name('posts.related');
Route::get('posts/{post}/comments', [CommentController::class, 'index'])->name('comments.index');
Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');

// Posts — writes (auth + Policy).
Route::post('posts', [PostController::class, 'store'])->middleware($auth)->name('posts.store');
Route::patch('posts/{post}', [PostController::class, 'update'])->middleware($auth)->name('posts.update');
Route::put('posts/{post}', [PostController::class, 'update'])->middleware($auth);
Route::delete('posts/{post}', [PostController::class, 'destroy'])->middleware($auth)->name('posts.destroy');
Route::post('posts/{post}/publish', [PostController::class, 'publish'])->middleware($auth)->name('posts.publish');
Route::post('posts/{post}/unpublish', [PostController::class, 'unpublish'])->middleware($auth)->name('posts.unpublish');

// Comments — store on a post, update/destroy by id (auth + Policy).
Route::post('posts/{post}/comments', [CommentController::class, 'store'])->middleware($auth)->name('comments.store');
Route::patch('comments/{comment}', [CommentController::class, 'update'])->middleware($auth)->name('comments.update');
Route::put('comments/{comment}', [CommentController::class, 'update'])->middleware($auth);
Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->middleware($auth)->name('comments.destroy');

// Categories — full REST.
Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
Route::post('categories', [CategoryController::class, 'store'])->middleware($auth)->name('categories.store');
Route::patch('categories/{category}', [CategoryController::class, 'update'])->middleware($auth)->name('categories.update');
Route::put('categories/{category}', [CategoryController::class, 'update'])->middleware($auth);
Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware($auth)->name('categories.destroy');

// Tags — index/show public; store/destroy for staff.
Route::get('tags', [TagController::class, 'index'])->name('tags.index');
Route::get('tags/{tag}', [TagController::class, 'show'])->name('tags.show');
Route::post('tags', [TagController::class, 'store'])->middleware($auth)->name('tags.store');
Route::delete('tags/{tag}', [TagController::class, 'destroy'])->middleware($auth)->name('tags.destroy');
