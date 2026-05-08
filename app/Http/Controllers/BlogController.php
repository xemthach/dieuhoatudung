<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostCategory;

class BlogController extends Controller
{
    /**
     * Trang danh sách bài viết (Blog hub).
     */
    public function index()
    {
        $posts = Post::with(['category', 'author'])
            ->where('status', PostStatus::Published)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->paginate(9);

        $categories = PostCategory::where('is_active', true)
            ->withCount(['posts' => fn ($q) => $q->where('status', PostStatus::Published)])
            ->orderBy('name')
            ->get();

        return view('blog.index', compact('posts', 'categories'));
    }

    /**
     * Trang chi tiết bài viết.
     */
    public function show(string $slug)
    {
        $post = Post::with(['category', 'author', 'tags', 'activeFaqs', 'products.brand'])
            ->where('slug', $slug)
            ->where('status', PostStatus::Published)
            ->firstOrFail();

        $relatedPosts = Post::with(['category', 'author'])
            ->where('post_category_id', $post->post_category_id)
            ->where('id', '!=', $post->id)
            ->where('status', PostStatus::Published)
            ->orderByDesc('published_at')
            ->take(3)
            ->get();

        return view('blog.show', compact('post', 'relatedPosts'));
    }
}
