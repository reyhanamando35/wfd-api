<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Illustration;
use App\Models\Illustrator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Perilaku yang berbeda antara MySQL dan PostgreSQL (produksi di Supabase).
 * Jalankan juga di PostgreSQL: DB_CONNECTION=pgsql ... vendor/bin/phpunit
 */
class DatabaseCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private Illustrator $illustrator;
    private string $illustratorToken;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.internal_api_key' => 'k']);

        $user = User::create(['name' => 'I', 'email' => 'i@example.com', 'password' => 'password', 'bio' => 'b', 'profile_picture' => 'assets/p.jpg']);
        $this->illustrator = Illustrator::create(['user_id' => $user->id, 'experience_years' => 1, 'is_open_commision' => false]);
        $this->illustratorToken = $user->createToken('auth_token_illustrator')->plainTextToken;

        $category = Category::create(['name' => 'Realism']);
        foreach ([['Sunset Realism', 0], ['Night Sky', 2], ['Pending Piece', 1]] as [$title, $isSold]) {
            Illustration::create([
                'title' => $title, 'description' => 'd', 'price' => 100, 'image_path' => 'assets/a.jpg', 'date_issued' => '2025-01-01',
                'illustrator_id' => $this->illustrator->id, 'category_id' => $category->id, 'is_sold' => $isSold,
            ]);
        }
    }

    private function api(?string $token = null): static
    {
        $this->app['auth']->forgetGuards();
        return $this->withHeaders(array_filter(['X-Internal-Key' => 'k', 'Accept' => 'application/json', 'Authorization' => $token ? "Bearer $token" : null]));
    }

    public function test_title_search_is_case_insensitive(): void
    {
        // PostgreSQL LIKE membedakan huruf besar-kecil; whereLike memakai ILIKE
        $this->api()->getJson('/api/market/filter?title=REALISM')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_listing_filter_by_sale_status_and_stats(): void
    {
        $response = $this->api($this->illustratorToken)->getJson('/api/illustrations/listings/filter')->assertOk();
        $response->assertJsonPath('data.stats.total', 3)->assertJsonPath('data.stats.sold', 1)->assertJsonPath('data.stats.available', 1);

        $this->api($this->illustratorToken)->getJson('/api/illustrations/listings/filter?is_sold=2')
            ->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.title', 'Night Sky');
    }

    public function test_admin_can_toggle_open_commission_boolean(): void
    {
        Admin::create(['email' => 'admin@example.com']);
        $adminToken = User::create(['name' => 'A', 'email' => 'admin@example.com', 'password' => 'password', 'bio' => 'b', 'profile_picture' => 'assets/p.jpg'])
            ->createToken('auth_token_admin')->plainTextToken;

        $this->api($adminToken)->putJson("/api/editIllustrator/{$this->illustrator->id}", [
            'name' => 'I', 'email' => 'i@example.com', 'experience_years' => 2, 'is_open_commision' => '1',
        ])->assertOk();

        $this->assertTrue($this->illustrator->fresh()->is_open_commision);
        $this->api()->getJson("/api/users/{$this->illustrator->user_id}")->assertJsonPath('data.is_open_commision', 1);
    }
}
