<?php

namespace Tests\Feature\Api;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_blog_flow(): void
    {
        $registerResponse = $this->postJson('/api/register', [
            'name' => 'Test Blogger',
            'email' => 'blogger@test.com',
            'password' => 'secure_password',
            'password_confirmation' => 'secure_password',
        ]);

        $registerResponse->assertStatus(201);
        $token = $registerResponse->json('access_token');
        $userId = $registerResponse->json('user.id');

        $this->assertNotNull($token);
        $this->assertDatabaseHas('users', ['email' => 'blogger@test.com']);

        $createPostResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/posts', [
                    'title' => 'My First API Post',
                    'text' => 'This post was created via automated integration test.',
                ]);

        $createPostResponse->assertStatus(201);
        $postId = $createPostResponse->json('post.id');

        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'user_id' => $userId,
            'title' => 'My First API Post',
        ]);

        $feedResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/posts');

        $feedResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'My First API Post');

        $myPostsResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/posts/my');

        $myPostsResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);

        $filterResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/posts?date_from=' . now()->toDateString());

        $filterResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->postJson('/api/posts', ['title' => 'Fail', 'text' => 'Fail'])
            ->assertStatus(401);

        $this->getJson('/api/posts')->assertStatus(401);
        $this->getJson('/api/posts/my')->assertStatus(401);
    }
}