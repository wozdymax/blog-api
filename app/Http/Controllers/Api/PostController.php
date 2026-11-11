<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

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
        $validated = $request->validate($this->listRules($request));
        $limit = (int) ($validated['limit'] ?? 10);
        $offset = (int) ($validated['offset'] ?? 0);
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';
        $query = Post::with('user:id,name');
        $this->applyDateFilter($query, $validated);
        $total = (clone $query)->count();
        $posts = $query
            ->orderBy($sortBy, $sortOrder)
            ->offset($offset)
            ->limit($limit)
            ->get();
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
                    ],
                ];
            })->values(),
            'meta' => [
                'total' => (int) $total,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    public function myPosts(Request $request)
    {
        $validated = $request->validate($this->listRules($request));
        $limit = (int) ($validated['limit'] ?? 10);
        $offset = (int) ($validated['offset'] ?? 0);
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';
        $query = Post::where('user_id', $request->user()->id);
        $this->applyDateFilter($query, $validated);
        $total = (clone $query)->count();
        $posts = $query
            ->orderBy($sortBy, $sortOrder)
            ->offset($offset)
            ->limit($limit)
            ->get();
        return response()->json([
            'data' => $posts->map(function ($post) {
                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'text' => $post->text,
                    'created_at' => $post->created_at->toISOString(),
                    'updated_at' => $post->updated_at->toISOString(),
                ];
            })->values(),
            'meta' => [
                'total' => (int) $total,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    private function listRules(Request $request): array
    {
        return [
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'sort_by' => ['nullable', 'string', 'in:created_at,title'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => array_filter([
                'nullable',
                'date_format:Y-m-d',
                $request->filled('date_from') ? 'after_or_equal:date_from' : null,
            ]),
        ];
    }
    private function applyDateFilter(Builder $query, array $validated): void
    {
        if (!empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }
        if (!empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }
    }
}
