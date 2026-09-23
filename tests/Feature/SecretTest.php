<?php

namespace Tests\Feature;

use App\Models\Secret;
use App\Models\SecretGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SecretTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_secrets(): void
    {
        $this->get(route('secrets.index'))->assertRedirect(route('login'));
        $this->get(route('secrets.create'))->assertRedirect(route('login'));

        $group = SecretGroup::factory()->create(['name' => 'Backend']);
        $this->get(route('secrets.group', $group))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_secrets_index(): void
    {
        $user = User::factory()->admin()->create();

        Secret::create([
            'key' => 'DB_PASSWORD',
            'value' => 'super-secret',
            'environment' => ['dev', 'production'],
        ]);

        $this->actingAs($user)
            ->get(route('secrets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Secrets/Index')
                ->has('groups.data', 0)
                ->where('groups.per_page', 15)
                ->where('groups.current_page', 1)
                ->where('filters.q', '')
                ->has('secrets.data', 1)
                ->where('secrets.data.0.key', 'DB_PASSWORD')
                ->where('secrets.data.0.environment.dev', true)
                ->where('secrets.data.0.environment.staging', false)
                ->where('secrets.data.0.environment.production', true)
                ->where('secrets.data.0.group', null)
                ->where('secrets.data.0.type', 'password')
                ->where('secrets.data.0.type_label', 'Password / API key')
                ->missing('secrets.data.0.value')
                ->where('secrets.per_page', 15)
                ->where('secrets.current_page', 1)
            );
    }

    public function test_secrets_index_is_paginated(): void
    {
        $user = User::factory()->admin()->create();

        for ($i = 1; $i <= 16; $i++) {
            Secret::create([
                'key' => sprintf('KEY_%02d', $i),
                'value' => "value-{$i}",
                'environment' => ['dev'],
            ]);
        }

        $this->actingAs($user)
            ->get(route('secrets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Secrets/Index')
                ->has('secrets.data', 15)
                ->where('secrets.total', 16)
                ->where('secrets.last_page', 2)
            );

        $this->actingAs($user)
            ->get(route('secrets.index', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('secrets.data', 1)
                ->where('secrets.current_page', 2)
            );
    }

    public function test_secrets_index_groups_are_paginated(): void
    {
        $user = User::factory()->admin()->create();

        SecretGroup::factory()->count(16)->create();

        $this->actingAs($user)
            ->get(route('secrets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Secrets/Index')
                ->has('groups.data', 15)
                ->where('groups.total', 16)
                ->where('groups.last_page', 2)
                ->where('groups.per_page', 15)
                ->where('groups.current_page', 1)
            );

        $this->actingAs($user)
            ->get(route('secrets.index', ['groups_page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('groups.data', 1)
                ->where('groups.current_page', 2)
            );
    }

    public function test_secrets_and_groups_pagination_are_independent(): void
    {
        $user = User::factory()->admin()->create();

        SecretGroup::factory()->count(16)->create();

        foreach (range(1, 16) as $i) {
            Secret::create([
                'key' => sprintf('KEY_%02d', $i),
                'value' => "value-{$i}",
                'environment' => ['dev'],
            ]);
        }

        $this->actingAs($user)
            ->get(route('secrets.index', ['page' => 2, 'groups_page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('groups.data', 1)
                ->where('groups.current_page', 2)
                ->has('secrets.data', 1)
                ->where('secrets.current_page', 2)
            );
    }

    public function test_user_can_create_secret(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('secrets.store'), [
                'type' => 'password',
                'key' => 'API_KEY',
                'value' => 'generated-password-123',
                'environment' => ['staging', 'production'],
            ])
            ->assertRedirect(route('secrets.index'));

        $this->assertDatabaseHas('secrets', [
            'key' => 'API_KEY',
        ]);

        $secret = Secret::query()->where('key', 'API_KEY')->first();
        $this->assertNotNull($secret);
        $this->assertSame('generated-password-123', $secret->value);
        $this->assertSame('password', $secret->type->value);
        $this->assertEqualsCanonicalizing(['staging', 'production'], $secret->environment);
        $this->assertNotSame('generated-password-123', $secret->getRawOriginal('value'));
        $this->assertNull($secret->secret_group_id);
        $this->assertNull($secret->description);
    }

    public function test_user_can_create_secret_with_human_readable_cyrillic_key(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('secrets.store'), [
                'type' => 'password',
                'key' => '  Пароль от базы   (прод)  ',
                'value' => 'secret',
                'environment' => ['production'],
            ])
            ->assertRedirect(route('secrets.index'));

        $this->assertDatabaseHas('secrets', [
            'key' => 'Пароль от базы (прод)',
        ]);
    }

    public function test_create_rejects_secret_key_with_control_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('secrets.create'))
            ->post(route('secrets.store'), [
                'type' => 'password',
                'key' => "API\x01KEY",
                'value' => 'secret',
                'environment' => ['dev'],
            ])
            ->assertRedirect(route('secrets.create'))
            ->assertSessionHasErrors('key');
    }

    public function test_human_readable_key_uniqueness_ignores_extra_spaces(): void
    {
        $user = User::factory()->create();

        Secret::factory()->create(['key' => 'Пароль от базы']);

        $this->actingAs($user)
            ->from(route('secrets.create'))
            ->post(route('secrets.store'), [
                'type' => 'password',
                'key' => '  Пароль   от   базы  ',
                'value' => 'secret',
                'environment' => ['dev'],
            ])
            ->assertRedirect(route('secrets.create'))
            ->assertSessionHasErrors('key');
    }

    public function test_user_can_create_secret_with_group(): void
    {
        $user = User::factory()->admin()->create();
        $group = SecretGroup::factory()->create(['name' => 'Backend']);

        $this->actingAs($user)
            ->post(route('secrets.store'), [
                'type' => 'password',
                'key' => 'API_KEY',
                'value' => 'generated-password-123',
                'environment' => ['staging'],
                'secret_group_id' => $group->id,
            ])
            ->assertRedirect(route('secrets.group', $group));

        $this->assertDatabaseHas('secrets', [
            'key' => 'API_KEY',
            'secret_group_id' => $group->id,
        ]);
    }

    public function test_secrets_index_shows_groups_and_ungrouped_separately(): void
    {
        $user = User::factory()->admin()->create();
        $group = SecretGroup::factory()->create(['name' => 'Infrastructure']);
        SecretGroup::factory()->create(['name' => 'Empty']);

        Secret::create([
            'key' => 'REDIS_PASSWORD',
            'value' => 'secret',
            'environment' => ['dev'],
            'secret_group_id' => $group->id,
        ]);

        Secret::create([
            'key' => 'LOOSE_TOKEN',
            'value' => 'loose',
            'environment' => ['dev'],
        ]);

        $this->actingAs($user)
            ->get(route('secrets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Secrets/Index')
                ->has('groups.data', 2)
                ->where('groups.data.0.name', 'Empty')
                ->where('groups.data.0.secrets_count', 0)
                ->where('groups.data.1.name', 'Infrastructure')
                ->where('groups.data.1.secrets_count', 1)
                ->has('secrets.data', 1)
                ->where('secrets.data.0.key', 'LOOSE_TOKEN')
                ->where('secrets.data.0.group', null)
            );
    }

    public function test_secrets_index_can_search_by_key(): void
    {
        $user = User::factory()->admin()->create();

        Secret::create([
            'key' => 'DB_PASSWORD',
            'value' => 'db',
            'environment' => ['dev'],
        ]);

        Secret::create([
            'key' => 'API_TOKEN',
            'value' => 'api',
            'environment' => ['dev'],
        ]);

        $this->actingAs($user)
            ->get(route('secrets.index', ['q' => 'api']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Secrets/Index')
                ->where('filters.q', 'api')
                ->has('secrets.data', 1)
                ->where('secrets.data.0.key', 'API_TOKEN')
            );
    }

    public function test_secrets_index_can_search_by_cyrillic_key(): void
    {
        $user = User::factory()->admin()->create();

        Secret::create([
            'key' => 'Пароль от базы',
            'value' => 'db',
            'environment' => ['dev'],
        ]);

        Secret::create([
            'key' => 'API_TOKEN',
            'value' => 'api',
            'environment' => ['dev'],
        ]);

        $this->actingAs($user)
            ->get(route('secrets.index', ['q' => 'базы']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Secrets/Index')
                ->has('secrets.data', 1)
                ->where('secrets.data.0.key', 'Пароль от базы')
            );
    }

    public function test_secrets_index_can_search_by_group_name(): void
    {
        $user = User::factory()->admin()->create();
        $backend = SecretGroup::factory()->create(['name' => 'Backend']);
        $frontend = SecretGroup::factory()->create(['name' => 'Frontend']);

        Secret::create([
            'key' => 'API_TOKEN',
            'value' => 'grouped',
            'environment' => ['dev'],
            'secret_group_id' => $backend->id,
        ]);

        Secret::create([
            'key' => 'UI_TOKEN',
            'value' => 'front',
            'environment' => ['dev'],
            'secret_group_id' => $frontend->id,
        ]);

        Secret::create([
            'key' => 'LOOSE_TOKEN',
            'value' => 'loose',
            'environment' => ['dev'],
        ]);

        $this->actingAs($user)
            ->get(route('secrets.index', ['q' => 'back']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Secrets/Index')
                ->has('groups.data', 1)
                ->where('groups.data.0.name', 'Backend')
                ->has('secrets.data', 1)
                ->where('secrets.data.0.key', 'API_TOKEN')
                ->where('secrets.data.0.group.id', $backend->id)
            );
    }

    public function test_search_by_key_includes_grouped_secrets(): void
    {
        $user = User::factory()->admin()->create();
        $group = SecretGroup::factory()->create(['name' => 'Infrastructure']);

        Secret::create([
            'key' => 'REDIS_PASSWORD',
            'value' => 'secret',
            'environment' => ['dev'],
            'secret_group_id' => $group->id,
        ]);

        $this->actingAs($user)
            ->get(route('secrets.index', ['q' => 'redis']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('secrets.data', 1)
                ->where('secrets.data.0.key', 'REDIS_PASSWORD')
                ->where('secrets.data.0.group.name', 'Infrastructure')
            );
    }

    public function test_user_can_view_secrets_inside_group(): void
    {
        $user = User::factory()->admin()->create();
        $group = SecretGroup::factory()->create(['name' => 'Backend']);
        $other = SecretGroup::factory()->create(['name' => 'Frontend']);

        Secret::create([
            'key' => 'API_TOKEN',
            'value' => 'grouped',
            'environment' => ['dev'],
            'secret_group_id' => $group->id,
        ]);

        Secret::create([
            'key' => 'OTHER_TOKEN',
            'value' => 'other',
            'environment' => ['dev'],
            'secret_group_id' => $other->id,
        ]);

        Secret::create([
            'key' => 'LOOSE_TOKEN',
            'value' => 'loose',
            'environment' => ['dev'],
        ]);

        $this->actingAs($user)
            ->get(route('secrets.group', $group))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Secrets/Group')
                ->where('group.id', $group->id)
                ->where('group.name', 'Backend')
                ->has('availableTags')
                ->has('secrets.data', 1)
                ->where('secrets.data.0.key', 'API_TOKEN')
                ->missing('secrets.data.0.value')
            );
    }

    public function test_create_form_can_preselect_group(): void
    {
        $user = User::factory()->admin()->create();
        $group = SecretGroup::factory()->create(['name' => 'Mobile']);
        $group->attachTags(['backend'], User::TAG_TYPE);

        $this->actingAs($user)
            ->get(route('secrets.create', ['group' => $group->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Secrets/Create')
                ->where('initialGroup.id', $group->id)
                ->where('initialGroup.name', 'Mobile')
                ->where('initialGroup.tags', ['backend'])
                ->where('defaultType', 'password')
                ->has('secretTypes', 3)
                ->where('secretTypes.0.slug', 'password')
                ->where('secretTypes.1.slug', 'file')
                ->where('secretTypes.2.slug', 'note')
                ->has('availableTags')
            );
    }

    public function test_create_rejects_unknown_group(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('secrets.create'))
            ->post(route('secrets.store'), [
                'type' => 'password',
                'key' => 'NO_GROUP',
                'value' => 'secret',
                'environment' => ['dev'],
                'secret_group_id' => 999,
            ])
            ->assertRedirect(route('secrets.create'))
            ->assertSessionHasErrors('secret_group_id');
    }

    public function test_secret_value_is_hidden_until_revealed(): void
    {
        $user = User::factory()->admin()->create();

        $secret = Secret::create([
            'key' => 'TOKEN',
            'value' => 'hidden-value',
            'environment' => ['dev'],
        ]);

        $this->actingAs($user)
            ->get(route('secrets.reveal', $secret))
            ->assertOk()
            ->assertJson(['value' => 'hidden-value']);
    }

    public function test_user_can_delete_secret(): void
    {
        $user = User::factory()->admin()->create();

        $secret = Secret::create([
            'key' => 'OLD',
            'value' => 'x',
            'environment' => ['dev'],
        ]);

        $this->actingAs($user)
            ->delete(route('secrets.destroy', $secret))
            ->assertRedirect();

        $this->assertDatabaseMissing('secrets', ['id' => $secret->id]);
    }

    public function test_create_requires_at_least_one_environment(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('secrets.create'))
            ->post(route('secrets.store'), [
                'type' => 'password',
                'key' => 'NO_ENV',
                'value' => 'secret',
                'environment' => [],
            ])
            ->assertRedirect(route('secrets.create'))
            ->assertSessionHasErrors('environment');
    }

    public function test_user_can_create_secret_with_tags(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->post(route('secrets.store'), [
                'type' => 'password',
                'key' => 'TAGGED_KEY',
                'value' => 'secret',
                'environment' => ['dev'],
                'tags' => ['backend', 'on-call'],
            ])
            ->assertRedirect(route('secrets.index'));

        $secret = Secret::query()->where('key', 'TAGGED_KEY')->first();

        $this->assertNotNull($secret);
        $this->assertTrue($secret->hasTag('backend', User::TAG_TYPE));
        $this->assertTrue($secret->hasTag('on-call', User::TAG_TYPE));
    }

    public function test_regular_user_sees_only_secrets_and_groups_with_matching_tags(): void
    {
        $user = User::factory()->create();
        $user->attachTags(['backend'], User::TAG_TYPE);

        $visibleGroup = SecretGroup::factory()->create(['name' => 'Backend']);
        $visibleGroup->attachTags(['backend'], User::TAG_TYPE);

        $hiddenGroup = SecretGroup::factory()->create(['name' => 'Billing']);
        $hiddenGroup->attachTags(['billing'], User::TAG_TYPE);

        $visible = Secret::factory()->create(['key' => 'VISIBLE_TOKEN']);
        $visible->attachTags(['backend'], User::TAG_TYPE);

        $hidden = Secret::factory()->create(['key' => 'HIDDEN_TOKEN']);
        $hidden->attachTags(['billing'], User::TAG_TYPE);

        $ungrouped = Secret::factory()->create(['key' => 'UNTAGGED_TOKEN']);

        $this->actingAs($user)
            ->get(route('secrets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Secrets/Index')
                ->has('groups.data', 1)
                ->where('groups.data.0.name', 'Backend')
                ->has('secrets.data', 1)
                ->where('secrets.data.0.key', 'VISIBLE_TOKEN')
            );

        $this->actingAs($user)->get(route('secrets.group', $hiddenGroup))->assertForbidden();
        $this->actingAs($user)->get(route('secrets.reveal', $hidden))->assertForbidden();
        $this->actingAs($user)->delete(route('secrets.destroy', $hidden))->assertForbidden();
        $this->actingAs($user)->get(route('secrets.reveal', $ungrouped))->assertForbidden();

        $this->assertDatabaseHas('secrets', ['id' => $hidden->id]);
    }

    public function test_regular_user_cannot_open_create_form_for_inaccessible_group(): void
    {
        $user = User::factory()->create();
        $user->attachTags(['backend'], User::TAG_TYPE);
        $group = SecretGroup::factory()->create(['name' => 'Billing']);
        $group->attachTags(['billing'], User::TAG_TYPE);

        $this->actingAs($user)
            ->get(route('secrets.create', ['group' => $group->id]))
            ->assertForbidden();
    }

    public function test_regular_user_cannot_attach_secret_to_inaccessible_group(): void
    {
        $user = User::factory()->create();
        $user->attachTags(['backend'], User::TAG_TYPE);
        $group = SecretGroup::factory()->create(['name' => 'Billing']);
        $group->attachTags(['billing'], User::TAG_TYPE);

        $this->actingAs($user)
            ->from(route('secrets.create'))
            ->post(route('secrets.store'), [
                'type' => 'password',
                'key' => 'NO_ACCESS',
                'value' => 'secret',
                'environment' => ['dev'],
                'secret_group_id' => $group->id,
            ])
            ->assertRedirect(route('secrets.create'))
            ->assertSessionHasErrors('secret_group_id');
    }
}
