<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_profile(): void
    {
        $this->get(route('profile.show'))->assertRedirect(route('login'));
        $this->patch(route('profile.password'), [
            'password' => 'new-password-123',
        ])->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_own_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Анна',
            'email' => 'anna@example.com',
        ]);
        $user->attachTags(['backend', 'on-call'], User::TAG_TYPE);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Show')
                ->where('profile.id', $user->id)
                ->where('profile.name', 'Анна')
                ->where('profile.email', 'anna@example.com')
                ->where('profile.is_admin', false)
                ->where('profile.tags', ['backend', 'on-call'])
            );
    }

    public function test_user_can_update_own_password(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password',
        ]);

        $this->actingAs($user)
            ->from(route('profile.show'))
            ->patch(route('profile.password'), [
                'password' => 'new-password-123',
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success', 'Пароль обновлён');

        $user->refresh();

        $this->assertTrue(Hash::check('new-password-123', $user->password));
        $this->assertFalse(Hash::check('old-password', $user->password));
    }

    public function test_own_password_update_requires_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.show'))
            ->patch(route('profile.password'), [
                'password' => '',
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHasErrors('password');
    }

    public function test_regular_user_cannot_update_another_users_password(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create([
            'password' => 'keep-this-password',
        ]);

        $this->actingAs($user)
            ->patch(route('users.password', $other), [
                'password' => 'hijacked-password',
            ])
            ->assertForbidden();

        $this->assertTrue(Hash::check('keep-this-password', $other->refresh()->password));
    }
}
