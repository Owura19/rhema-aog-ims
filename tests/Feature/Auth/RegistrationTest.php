<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Public registration is disabled on purpose (routes/auth.php): members and staff
 * are added by the church, never by self-registration on the website.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_registration_page_does_not_exist(): void
    {
        $this->get('/register')->assertNotFound();
        $this->assertFalse(Route::has('register'));
    }

    public function test_nobody_can_register_by_posting_to_register(): void
    {
        $this->post('/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }
}
