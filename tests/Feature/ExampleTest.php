<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/upload')->assertRedirect(route('login'));
        $this->get('/secrets')->assertRedirect(route('login'));
        $this->get('/users')->assertRedirect(route('login'));
    }

    public function test_login_page_is_accessible(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
            );
    }

    public function test_authenticated_user_can_view_files_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Files/Index')
                ->has('files')
                ->has('groups')
                ->has('availableTags')
                ->where('auth.user.email', $user->email)
            );
    }

    public function test_regular_user_cannot_access_users_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_users_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->has('users')
                ->has('availableTags')
            );
    }

    public function test_user_can_log_in_and_log_out(): void
    {
        $user = User::factory()->create([
            'email' => 'admin',
            'password' => 'secret-pass',
        ]);

        $this->post(route('login'), [
            'login' => 'admin',
            'password' => 'secret-pass',
        ])->assertRedirect(route('files.index'));

        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_can_create_users(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'login' => 'newuser',
                'password' => 'password123',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'newuser',
            'name' => 'newuser',
            'is_admin' => false,
        ]);
    }

    public function test_admin_can_create_user_with_new_tags(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('users.index'))
            ->post(route('users.store'), [
                'login' => 'tagged',
                'password' => 'password123',
                'tags' => ['backend', 'on-call'],
            ])
            ->assertRedirect(route('users.index'));

        $created = User::query()->where('email', 'tagged')->first();

        $this->assertNotNull($created);
        $this->assertTrue($created->hasTag('backend', User::TAG_TYPE));
        $this->assertTrue($created->hasTag('on-call', User::TAG_TYPE));

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->has('availableTags')
                ->where('availableTags', fn ($tags) => collect($tags)->intersect(['backend', 'on-call'])->count() === 2)
                ->has('users', 2)
                ->where('users', fn ($users) => collect($users)->contains(
                    fn ($user) => $user['email'] === 'tagged'
                        && collect($user['tags'])->sort()->values()->all() === ['backend', 'on-call']
                ))
            );
    }

    public function test_admin_can_update_user_name_and_tags(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create([
            'name' => 'old-name',
            'email' => 'target-user',
        ]);
        $target->attachTags(['backend', 'on-call'], User::TAG_TYPE);

        $this->actingAs($admin)
            ->from(route('users.index'))
            ->patch(route('users.update', $target), [
                'name' => 'Новое имя',
                'tags' => ['backend', 'devops'],
            ])
            ->assertRedirect(route('users.index'));

        $target->refresh()->load('tags');

        $this->assertSame('Новое имя', $target->name);
        $this->assertSame('target-user', $target->email);
        $this->assertTrue($target->hasTag('backend', User::TAG_TYPE));
        $this->assertTrue($target->hasTag('devops', User::TAG_TYPE));
        $this->assertFalse($target->hasTag('on-call', User::TAG_TYPE));
    }

    public function test_regular_user_cannot_update_user(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $target = User::factory()->create(['name' => 'keep']);

        $this->actingAs($user)
            ->patch(route('users.update', $target), [
                'name' => 'hacked',
                'tags' => ['x'],
            ])
            ->assertForbidden();

        $this->assertSame('keep', $target->fresh()->name);
    }

    public function test_admin_can_change_user_password(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create([
            'password' => 'old-password',
        ]);

        $this->actingAs($admin)
            ->from(route('users.index'))
            ->patch(route('users.password', $target), [
                'password' => 'password123',
            ])
            ->assertRedirect(route('users.index'));

        $target->refresh();

        $this->assertTrue(Hash::check('password123', $target->password));
        $this->assertFalse(Hash::check('old-password', $target->password));
    }

    public function test_regular_user_cannot_change_user_password(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $target = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('users.password', $target), [
                'password' => 'password123',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create([
            'email' => 'to-delete',
        ]);

        $this->actingAs($admin)
            ->from(route('users.index'))
            ->delete(route('users.destroy', $target))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', [
            'id' => $target->id,
        ]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('users.index'))
            ->delete(route('users.destroy', $admin))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
        ]);
    }

    public function test_regular_user_cannot_delete_user(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $target = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('users.destroy', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
        ]);
    }

    public function test_admin_can_create_admin_users(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'login' => 'second-admin',
                'password' => 'password123',
                'is_admin' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'second-admin',
            'is_admin' => true,
        ]);
    }

    public function test_user_create_command_creates_admin_when_first(): void
    {
        $this->artisan('user:create', [
            'login' => 'root',
            '--password' => 'password123',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('администратор');

        $this->assertDatabaseHas('users', [
            'email' => 'root',
            'is_admin' => true,
        ]);

        $this->assertTrue(Hash::check('password123', User::query()->where('email', 'root')->first()->password));
    }

    public function test_user_create_command_respects_admin_flag(): void
    {
        User::factory()->admin()->create();

        $this->artisan('user:create', [
            'login' => 'regular',
            '--password' => 'password123',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'regular',
            'is_admin' => false,
        ]);

        $this->artisan('user:create', [
            'login' => 'boss',
            '--password' => 'password123',
            '--admin' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'boss',
            'is_admin' => true,
        ]);
    }

    public function test_authenticated_user_can_view_upload_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('files.upload'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Files/Upload')
                ->has('tusEndpoint')
                ->has('downloadBase')
                ->has('availableTags')
            );
    }
}
