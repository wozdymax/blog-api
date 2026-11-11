<?php

namespace Tests\Feature\Posts;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_all_posts(): void
    {
        $user = User::factory()->create();

        Post::factory()->count(5)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/posts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'text',
                        'created_at',
                        'updated_at',
                        'user' => [
                            'id',
                            'name',
                        ]
                    ]
                ],
                'meta' => [
                    'total',
                    'limit',
                    'offset',
                ]
            ]);
    }

    public function test_posts_are_paginated(): void
    {
        $user = User::factory()->create();
        Post::factory()->count(15)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/posts?limit=5&offset=0');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.limit', 5)
            ->assertJsonPath('meta.offset', 0);
    }

    public function test_posts_can_be_sorted_by_title(): void
    {
        $user = User::factory()->create();

        Post::factory()->create(['user_id' => $user->id, 'title' => 'B Post']);
        Post::factory()->create(['user_id' => $user->id, 'title' => 'A Post']);
        Post::factory()->create(['user_id' => $user->id, 'title' => 'C Post']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/posts?sort_by=title&sort_order=asc');

        $response->assertStatus(200);

        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertEquals(['A Post', 'B Post', 'C Post'], $titles);
    }

    public function test_posts_can_be_sorted_by_date(): void
    {
        $user = User::factory()->create();

        $post1 = Post::factory()->create(['user_id' => $user->id, 'created_at' => now()->subDays(2)]);
        $post2 = Post::factory()->create(['user_id' => $user->id, 'created_at' => now()->subDay()]);
        $post3 = Post::factory()->create(['user_id' => $user->id, 'created_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/posts?sort_by=created_at&sort_order=desc');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertEquals([$post3->id, $post2->id, $post1->id], $ids);
    }

    public function test_unauthenticated_user_cannot_get_posts(): void
    {
        $response = $this->getJson('/api/posts');

        $response->assertStatus(401);
    }
}