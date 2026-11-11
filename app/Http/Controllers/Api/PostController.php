<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'text' => ['required', 'string'],
        ]);

        $post = $request->user()->posts()->create($validated);

        return response()->json([
            'message' => 'Post created successfully',
            'post' => $post
        ], 201);
    }
    public function index(Request $request)
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'sort_by' => ['nullable', 'string', 'in:created_at,title'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
        ]);
        $limit = (int) ($validated['limit'] ?? 10);
        $offset = (int) ($validated['offset'] ?? 0);

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';

        $posts = Post::with('user:id,name')
            ->orderBy($sortBy, $sortOrder)
            ->offset($offset)
            ->limit($limit)
            ->get();

        $total = Post::count();

        return response()->json([
            'data' => $posts->map(function ($post) {
                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'text' => $post->text,
                    'created_at' => $post->created_at->toISOString(),
                    'updated_at' => $post->updated_at->toISOString(),
                    'user' => [
                        'id' => $post->user->id,
                        'name' => $post->user->name,
                    ]
                ];
            })->values(),
            'meta' => [
                'total' => (int) $total,
                'limit' => (int) $limit,
                'offset' => (int) $offset,
            ]
        ]);
    }
}
