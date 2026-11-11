<?php

namespace Tests\Feature\Posts;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetMyPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_only_their_own_posts(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Post::factory()->count(3)->create(['user_id' => $user1->id, 'title' => 'User 1 Post']);
        Post::factory()->count(2)->create(['user_id' => $user2->id, 'title' => 'User 2 Post']);

        $response = $this->actingAs($user1, 'sanctum')
            ->getJson('/api/posts/my');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);

        $titles = collect($response->json('data'))->pluck('title')->unique()->toArray();
        $this->assertEquals(['User 1 Post'], $titles);
    }

    public function test_my_posts_are_paginated_correctly(): void
    {
        $user = User::factory()->create();
        Post::factory()->count(15)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/posts/my?limit=5&offset=5');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.limit', 5)
            ->assertJsonPath('meta.offset', 5);
    }

    public function test_my_posts_can_be_sorted(): void
    {
        $user = User::factory()->create();

        $post1 = Post::factory()->create(['user_id' => $user->id, 'title' => 'Zebra', 'created_at' => now()->subDays(2)]);
        $post2 = Post::factory()->create(['user_id' => $user->id, 'title' => 'Apple', 'created_at' => now()->subDay()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/posts/my?sort_by=title&sort_order=asc');

        $response->assertStatus(200);

        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertEquals(['Apple', 'Zebra'], $titles);
    }

    public function test_unauthenticated_user_cannot_get_my_posts(): void
    {
        $response = $this->getJson('/api/posts/my');

        $response->assertStatus(401);
    }
}