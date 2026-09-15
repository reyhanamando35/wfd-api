<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Alur utama marketplace lewat API: registrasi, login, jual, beli, verifikasi admin, reset password, logout.
 */
class MarketplaceFlowTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.internal_api_key' => 'k']);
        $this->category = Category::create(['name' => 'Realism']);
    }

    private function api(?string $token = null): static
    {
        $this->app['auth']->forgetGuards();
        return $this->withHeaders(array_filter(['X-Internal-Key' => 'k', 'Accept' => 'application/json', 'Authorization' => $token ? "Bearer $token" : null]));
    }

    private function registerAndLoginCustomer(string $email): string
    {
        $this->api()->postJson('/api/register/customer', [
            'name' => 'Cust', 'email' => $email, 'password' => 'secret123', 'bio' => 'I buy art', 'profile_picture' => 'storage/profile_pictures/c.png',
        ])->assertCreated();

        return $this->api()->postJson('/api/login/customer', ['email' => $email, 'password' => 'secret123'])
            ->assertOk()->json('data.token');
    }

    private function registerAndLoginIllustrator(): string
    {
        $this->api()->postJson('/api/register/illustrator', [
            'name' => 'Illu', 'email' => 'illu@example.com', 'password' => 'secret123', 'bio' => 'I draw', 'profile_picture' => 'storage/profile_pictures/i.png',
            'experience_years' => 4, 'portofolio_link' => 'https://example.com', 'is_open_commision' => true,
        ])->assertCreated();

        return $this->api()->postJson('/api/login/illustrator', ['email' => 'illu@example.com', 'password' => 'secret123'])
            ->assertOk()->json('data.token');
    }

    private function adminToken(): string
    {
        Admin::create(['email' => 'admin@example.com']);
        return $this->api()->postJson('/api/admin/check-email', ['email' => 'admin@example.com', 'name' => 'Admin'])
            ->assertOk()->json('data.token');
    }

    public function test_registration_and_role_specific_login(): void
    {
        $this->registerAndLoginCustomer('cust@example.com');

        $this->api()->postJson('/api/register/customer', [
            'name' => 'Dup', 'email' => 'cust@example.com', 'password' => 'secret123', 'bio' => 'x', 'profile_picture' => 'storage/p.png',
        ])->assertUnprocessable();

        $this->api()->postJson('/api/login/customer', ['email' => 'cust@example.com', 'password' => 'wrong-pass'])->assertUnauthorized();
        $this->api()->postJson('/api/login/illustrator', ['email' => 'cust@example.com', 'password' => 'secret123'])->assertForbidden();
        $this->api()->postJson('/api/admin/check-email', ['email' => 'cust@example.com', 'name' => 'x'])->assertUnauthorized();
    }

    public function test_sell_buy_verify_flow(): void
    {
        $illustrator = $this->registerAndLoginIllustrator();
        $customer = $this->registerAndLoginCustomer('buyer@example.com');
        $admin = $this->adminToken();

        // Illustrator menjual karya
        $artId = $this->api($illustrator)->postJson('/api/illustrations', [
            'title' => 'Sunrise', 'description' => 'Morning light', 'price' => 250000, 'date_issued' => '2025-02-01',
            'category_id' => $this->category->id, 'image_path' => 'https://cdn.example.com/sunrise.png',
        ])->assertCreated()->json('data.id');

        $this->api()->getJson('/api/market/illustrations')->assertOk()->assertJsonPath('data.illustrations.0.title', 'Sunrise');
        $this->api()->getJson("/api/illustrations/$artId")->assertOk()->assertJsonPath('data.category.name', 'Realism');
        $this->api($illustrator)->getJson('/api/illustrator/listings')->assertOk()->assertJsonCount(1, 'data');

        // Customer membeli: karya jadi pending dan hilang dari daftar yang masih tersedia
        $purchaseId = $this->api($customer)->postJson('/api/purchase', [
            'illustration_id' => $artId, 'payment_method' => 'bri', 'file_path' => 'private/proofs/p.png',
        ])->assertCreated()->json('data.id');

        $this->api()->getJson('/api/illustrations/dashboard')->assertOk()->assertJsonCount(0, 'data');
        $this->api($customer)->getJson('/api/collections')->assertOk()->assertJsonCount(1, 'data');
        $this->api($customer)->getJson('/api/histories')->assertOk()->assertJsonPath('data.0.is_verified', 0);

        // Admin melihat dan memverifikasi
        $this->api($admin)->getJson('/api/purchases')->assertOk()->assertJsonPath('data.0.id', $purchaseId);
        $this->api($admin)->postJson("/api/purchases/$purchaseId/verify")->assertOk();

        $this->api($customer)->getJson('/api/histories')->assertJsonPath('data.0.is_verified', 1);
        $this->api()->getJson("/api/illustrations/$artId")->assertJsonPath('data.is_sold', 2);
        $this->api($admin)->getJson('/api/purchases')->assertJsonCount(0, 'data');
        $this->api($customer)->postJson('/api/purchase', [
            'illustration_id' => $artId, 'payment_method' => 'bri', 'file_path' => 'private/proofs/p2.png',
        ])->assertStatus(409);
    }

    public function test_admin_can_edit_and_delete_users(): void
    {
        $admin = $this->adminToken();
        $this->registerAndLoginCustomer('edit-me@example.com');
        $user = User::where('email', 'edit-me@example.com')->firstOrFail();

        $this->api($admin)->getJson('/api/customers')->assertOk()->assertJsonPath('data.0.user.email', 'edit-me@example.com');
        $this->api($admin)->putJson("/api/editCustomer/{$user->customer->id}", ['name' => 'Renamed', 'email' => 'renamed@example.com', 'bio' => 'new'])->assertOk();
        $this->assertSame('Renamed', $user->fresh()->name);

        $this->api($admin)->deleteJson("/api/users/{$user->id}")->assertOk();
        $this->assertModelMissing($user);
    }

    public function test_password_reset_flow_is_single_use_and_expires(): void
    {
        $this->registerAndLoginCustomer('forgot@example.com');
        $token = str_repeat('a', 64);
        DB::table('password_reset_tokens')->insert(['email' => 'forgot@example.com', 'token' => hash('sha256', $token), 'created_at' => now()]);

        $this->api()->postJson('/api/validasiPW', ['token' => $token])->assertOk();
        $this->api()->postJson('/api/validasiPassword', ['token' => $token, 'password' => 'brandnew1', 'confirmPassword' => 'brandnew1'])->assertOk();

        $this->assertTrue(Hash::check('brandnew1', User::where('email', 'forgot@example.com')->value('password')));
        $this->api()->postJson('/api/login/customer', ['email' => 'forgot@example.com', 'password' => 'brandnew1'])->assertOk();
        $this->api()->postJson('/api/validasiPW', ['token' => $token])->assertStatus(400); // sekali pakai

        $old = str_repeat('b', 64);
        DB::table('password_reset_tokens')->insert(['email' => 'forgot@example.com', 'token' => hash('sha256', $old), 'created_at' => now()->subMinutes(61)]);
        $this->api()->postJson('/api/validasiPW', ['token' => $old])->assertStatus(400); // kedaluwarsa
    }

    public function test_logout_revokes_token(): void
    {
        $token = $this->registerAndLoginCustomer('bye@example.com');

        $this->api($token)->getJson('/api/collections')->assertOk();
        $this->api($token)->postJson('/api/logout')->assertOk();
        $this->api($token)->getJson('/api/collections')->assertUnauthorized();
    }
}
