<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Illustration;
use App\Models\Illustrator;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setiap tes mencoba satu celah yang ditemukan saat audit keamanan Illustrasia.
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-internal-key';

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.internal_api_key' => self::KEY]);
        $this->category = Category::create(['name' => 'Realism']);
    }

    private function api(?string $token = null): static
    {
        // Dalam satu tes, guard sanctum menyimpan user dari request sebelumnya; di server tiap request berdiri sendiri
        $this->app['auth']->forgetGuards();

        $headers = ['X-Internal-Key' => self::KEY, 'Accept' => 'application/json'];
        if ($token) {
            $headers['Authorization'] = "Bearer $token";
        }
        return $this->withHeaders($headers);
    }

    private function user(string $email): User
    {
        return User::create(['name' => 'U', 'email' => $email, 'password' => 'password', 'bio' => 'b', 'profile_picture' => 'assets/pfp.jpg']);
    }

    private function customerToken(string $email = 'cust@example.com'): string
    {
        $user = $this->user($email);
        Customer::create(['user_id' => $user->id]);
        return $user->createToken('auth_token_customer')->plainTextToken;
    }

    private function illustrator(string $email = 'illu@example.com'): Illustrator
    {
        return Illustrator::create(['user_id' => $this->user($email)->id, 'experience_years' => 3]);
    }

    private function artwork(): Illustration
    {
        return Illustration::create([
            'title' => 'Art', 'description' => 'd', 'price' => 1000, 'image_path' => 'assets/art/a.jpg',
            'date_issued' => '2025-01-01', 'illustrator_id' => $this->illustrator()->id, 'category_id' => $this->category->id,
        ]);
    }

    private function adminToken(): string
    {
        Admin::create(['email' => 'admin@example.com']);
        return $this->user('admin@example.com')->createToken('auth_token_admin')->plainTextToken;
    }

    public function test_api_rejects_calls_without_internal_key(): void
    {
        $this->getJson('/api/market/illustrations')->assertUnauthorized();
        $this->withHeaders(['X-Internal-Key' => 'wrong'])->getJson('/api/market/illustrations')->assertUnauthorized();
        $this->postJson('/api/login/customer', ['email' => 'a@b.c', 'password' => 'x'])->assertUnauthorized();

        $this->api()->getJson('/api/market/illustrations')->assertOk();
    }

    public function test_customer_cannot_list_artwork(): void
    {
        $this->api($this->customerToken())->postJson('/api/illustrations', [
            'title' => 'x', 'description' => 'x', 'price' => 1, 'date_issued' => '2025-01-01',
            'category_id' => $this->category->id, 'image_path' => 'storage/uploads/a.jpg',
        ])->assertForbidden();
    }

    public function test_dangerous_file_paths_are_rejected(): void
    {
        $art = $this->artwork();
        $token = $this->customerToken();

        foreach (['JavaScript://%0aalert(1)', 'javascript:alert(1)', 'data:text/html,<script>', '"><img src=x onerror=alert(1)>'] as $path) {
            $this->api($token)->postJson('/api/purchase', ['illustration_id' => $art->id, 'payment_method' => 'bca', 'file_path' => $path])
                ->assertUnprocessable();
        }

        $this->api()->postJson('/api/register/customer', [
            'name' => 'n', 'email' => 'new@example.com', 'password' => 'password', 'bio' => 'b', 'profile_picture' => 'JavaScript://%0aalert(1)',
        ])->assertUnprocessable();

        $this->api($token)->postJson('/api/purchase', ['illustration_id' => $art->id, 'payment_method' => 'bca', 'file_path' => 'https://cdn.example.com/proof.jpg'])
            ->assertCreated();
    }

    public function test_artwork_cannot_be_bought_twice(): void
    {
        $art = $this->artwork();
        $payload = ['illustration_id' => $art->id, 'payment_method' => 'bca', 'file_path' => 'storage/uploads/proofs/p.jpg'];

        $this->api($this->customerToken('a@example.com'))->postJson('/api/purchase', $payload)->assertCreated();
        $this->api($this->customerToken('b@example.com'))->postJson('/api/purchase', $payload)->assertStatus(409);

        $this->assertSame(1, Purchase::count());
        $this->assertSame(1, $art->fresh()->is_sold);
    }

    public function test_rejected_purchase_is_marked_rejected_and_cannot_be_verified_later(): void
    {
        $art = $this->artwork();
        $admin = $this->adminToken();
        $payload = ['illustration_id' => $art->id, 'payment_method' => 'bca', 'file_path' => 'storage/uploads/proofs/p.jpg'];

        $this->api($this->customerToken('a@example.com'))->postJson('/api/purchase', $payload)->assertCreated();
        $rejected = Purchase::first();
        $this->api($admin)->postJson("/api/purchases/{$rejected->id}/reject")->assertOk();

        $this->assertSame(2, (int) $rejected->fresh()->is_verified);
        $this->assertSame(0, $art->fresh()->is_sold);

        // Customer lain membeli karya yang sama; pembelian lama yang ditolak tidak boleh muncul atau diverifikasi
        $this->api($this->customerToken('b@example.com'))->postJson('/api/purchase', $payload)->assertCreated();
        $this->assertSame([Purchase::latest('id')->first()->id], collect($this->api($admin)->getJson('/api/purchases')->json('data'))->pluck('id')->all());
        $this->api($admin)->postJson("/api/purchases/{$rejected->id}/verify")->assertStatus(409);
    }

    public function test_public_profile_hides_customer_email(): void
    {
        $customer = $this->user('private@example.com');
        Customer::create(['user_id' => $customer->id]);
        $illustrator = $this->illustrator('contact@example.com');

        $this->api()->getJson("/api/users/{$customer->id}")->assertOk()->assertJsonMissingPath('data.user.email');
        $this->api()->getJson("/api/users/{$illustrator->user_id}")->assertOk()->assertJsonPath('data.user.email', 'contact@example.com');
    }

    public function test_forgot_password_does_not_reveal_registered_emails(): void
    {
        $this->user('known@example.com');

        $unknown = $this->api()->postJson('/api/submitEmail', ['email' => 'nobody@example.com'])->assertOk()->json('message');
        $known = $this->api()->postJson('/api/submitEmail', ['email' => 'known@example.com'])->assertOk()->json('message');

        $this->assertSame($unknown, $known);
    }

    public function test_collection_sort_column_cannot_be_injected(): void
    {
        $this->api($this->customerToken())
            ->getJson('/api/collections/filter?sort_by=' . urlencode('(select 1)') . '&sort_order=' . urlencode('desc;drop table users'))
            ->assertOk();
    }

    public function test_admin_endpoints_still_require_admin_token(): void
    {
        $this->api()->getJson('/api/customers')->assertUnauthorized();
        $this->api($this->customerToken())->getJson('/api/customers')->assertUnauthorized();
        $this->api($this->adminToken())->getJson('/api/customers')->assertOk();
    }
}
