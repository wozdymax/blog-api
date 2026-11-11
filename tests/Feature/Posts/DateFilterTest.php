<?php

namespace Tests\Feature\Posts;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Old post',
            'created_at' => '2026-10-01 10:00:00',
        ]);

        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Middle post',
            'created_at' => '2026-10-15 23:30:00',
        ]);

        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'New post',
            'created_at' => '2026-11-01 08:00:00',
        ]);
    }

    public function test_posts_can_be_filtered_by_date_from(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/posts?date_from=2026-10-15');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertNotContains('Old post', $titles);
    }

    public function test_posts_can_be_filtered_by_date_to(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/posts?date_to=2026-10-15');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertNotContains('New post', $titles);
        $this->assertContains('Middle post', $titles);
    }

    public function test_posts_can_be_filtered_by_date_range(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/posts?date_from=2026-10-10&date_to=2026-10-20');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Middle post');
    }

    public function test_date_filter_works_with_pagination_and_sorting(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/posts?date_from=2026-10-01&sort_by=title&sort_order=asc&limit=1&offset=1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.limit', 1)
            ->assertJsonPath('meta.offset', 1)
            ->assertJsonPath('data.0.title', 'New post');
    }

    public function test_date_filter_applies_to_my_posts_endpoint(): void
    {
        $otherUser = User::factory()->create();
        Post::factory()->create([
            'user_id' => $otherUser->id,
            'title' => 'Other user post',
            'created_at' => '2026-10-15 12:00:00',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/posts/my?date_from=2026-10-10&date_to=2026-10-20');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Middle post');
    }

    public function test_invalid_date_format_returns_validation_error(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/posts?date_from=15-10-2026');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_from']);
    }

    public function test_date_to_cannot_be_before_date_from(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/posts?date_from=2026-11-01&date_to=2026-10-01');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_empty_result_when_no_posts_in_range(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/posts?date_from=2027-01-01');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }
}