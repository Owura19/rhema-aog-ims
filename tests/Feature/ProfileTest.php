<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The stock Breeze profile page (edit own name/email, delete own account) is not
 * routed in this app. These tests lock that in; the member portal's own
 * /portal/profile page is separate and unaffected.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_there_is_no_self_service_profile_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertNotFound();
        $this->actingAs($user)->patch('/profile', ['name' => 'Changed', 'email' => 'changed@example.com'])->assertNotFound();
        $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertNotFound();

        $this->assertSame($user->email, $user->fresh()?->email, 'the account is unchanged and still exists');
    }
}
