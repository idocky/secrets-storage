<?php

namespace Tests\Feature;

use App\Models\Secret;
use App\Models\SecretGroup;
use App\Models\User;
use Database\Seeders\SecretGroupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_secret_groups(): void
    {
        $group = SecretGroup::factory()->create(['name' => 'Backend']);

        $this->get(route('secret-groups.search'))->assertRedirect(route('login'));
        $this->post(route('secret-groups.store'), ['name' => 'Backend'])->assertRedirect(route('login'));
        $this->patch(route('secret-groups.tags', $group), ['tags' => ['backend']])->assertRedirect(route('login'));
    }

    public function test_user_can_create_secret_group_from_secrets_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('secrets.index'))
            ->post(route('secret-groups.store'), [
                'name' => 'Infrastructure',
            ])
            ->assertRedirect(route('secrets.index'))
            ->assertSessionHas('created_group.name', 'Infrastructure');

        $this->assertDatabaseHas('secret_groups', [
            'name' => 'Infrastructure',
        ]);
    }

    public function test_secret_group_name_must_be_unique(): void
    {
        $user = User::factory()->create();
        SecretGroup::factory()->create(['name' => 'Backend']);

        $this->actingAs($user)
            ->from(route('secrets.index'))
            ->post(route('secret-groups.store'), [
                'name' => 'Backend',
            ])
            ->assertRedirect(route('secrets.index'))
            ->assertSessionHasErrors('name');
    }

    public function test_user_can_search_secret_groups_by_name(): void
    {
        $user = User::factory()->admin()->create();
        SecretGroup::factory()->create(['name' => 'Backend']);
        SecretGroup::factory()->create(['name' => 'Frontend']);
        SecretGroup::factory()->create(['name' => 'Infrastructure']);

        $this->actingAs($user)
            ->getJson(route('secret-groups.search', ['q' => 'front']))
            ->assertOk()
            ->assertJsonCount(1, 'groups')
            ->assertJsonPath('groups.0.name', 'Frontend')
            ->assertJsonMissingPath('groups.1');
    }

    public function test_search_without_query_returns_groups(): void
    {
        $user = User::factory()->admin()->create();
        SecretGroup::factory()->create(['name' => 'Auth']);
        SecretGroup::factory()->create(['name' => 'Billing']);

        $this->actingAs($user)
            ->getJson(route('secret-groups.search'))
            ->assertOk()
            ->assertJsonCount(2, 'groups')
            ->assertJsonPath('groups.0.name', 'Auth')
            ->assertJsonPath('groups.1.name', 'Billing');
    }

    public function test_search_can_load_group_by_id(): void
    {
        $user = User::factory()->admin()->create();
        $group = SecretGroup::factory()->create(['name' => 'Mobile']);
        SecretGroup::factory()->create(['name' => 'Other']);

        $this->actingAs($user)
            ->getJson(route('secret-groups.search', ['id' => $group->id]))
            ->assertOk()
            ->assertJsonCount(1, 'groups')
            ->assertJsonPath('groups.0.id', $group->id)
            ->assertJsonPath('groups.0.name', 'Mobile');
    }

    public function test_user_can_delete_secret_group(): void
    {
        $user = User::factory()->admin()->create();
        $group = SecretGroup::factory()->create(['name' => 'Old']);
        $secret = Secret::factory()->forGroup($group)->create();

        $this->actingAs($user)
            ->delete(route('secret-groups.destroy', $group))
            ->assertRedirect(route('secrets.index'));

        $this->assertDatabaseMissing('secret_groups', ['id' => $group->id]);
        $this->assertDatabaseHas('secrets', [
            'id' => $secret->id,
            'secret_group_id' => null,
        ]);
    }

    public function test_factory_can_create_many_unique_groups(): void
    {
        $groups = SecretGroup::factory()->count(20)->create();

        $this->assertCount(20, $groups);
        $this->assertCount(20, $groups->pluck('name')->unique());
    }

    public function test_factory_can_create_group_with_secrets(): void
    {
        $group = SecretGroup::factory()->withSecrets(2)->create();

        $this->assertDatabaseCount('secrets', 2);
        $this->assertCount(2, $group->secrets);
        $this->assertTrue($group->secrets->every(fn (Secret $secret) => $secret->secret_group_id === $group->id));
    }

    public function test_seeder_creates_presets_and_fills_up_to_target(): void
    {
        $this->seed(SecretGroupSeeder::class);

        $this->assertSame(20, SecretGroup::query()->count());
        $this->assertDatabaseHas('secret_groups', ['name' => 'Backend']);
        $this->assertDatabaseHas('secret_groups', ['name' => 'Infrastructure']);
        $this->assertDatabaseHas('secret_groups', ['name' => 'Third-party']);

        $this->seed(SecretGroupSeeder::class);

        $this->assertSame(20, SecretGroup::query()->count());
    }

    public function test_user_can_create_secret_group_with_tags(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->from(route('secrets.index'))
            ->post(route('secret-groups.store'), [
                'name' => 'Platform',
                'tags' => ['backend', 'infra'],
            ])
            ->assertRedirect(route('secrets.index'))
            ->assertSessionHas('created_group.tags', ['backend', 'infra']);

        $group = SecretGroup::query()->where('name', 'Platform')->first();

        $this->assertNotNull($group);
        $this->assertTrue($group->hasTag('backend', User::TAG_TYPE));
        $this->assertTrue($group->hasTag('infra', User::TAG_TYPE));
    }

    public function test_search_returns_group_tags_and_hides_inaccessible_groups(): void
    {
        $user = User::factory()->create();
        $user->attachTags(['backend'], User::TAG_TYPE);

        $visible = SecretGroup::factory()->create(['name' => 'Backend']);
        $visible->attachTags(['backend'], User::TAG_TYPE);

        $hidden = SecretGroup::factory()->create(['name' => 'Billing']);
        $hidden->attachTags(['billing'], User::TAG_TYPE);

        $this->actingAs($user)
            ->getJson(route('secret-groups.search'))
            ->assertOk()
            ->assertJsonCount(1, 'groups')
            ->assertJsonPath('groups.0.name', 'Backend')
            ->assertJsonPath('groups.0.tags', ['backend']);

        $this->actingAs($user)
            ->delete(route('secret-groups.destroy', $hidden))
            ->assertForbidden();

        $this->assertDatabaseHas('secret_groups', ['id' => $hidden->id]);
    }

    public function test_user_can_update_group_tags_on_group_page(): void
    {
        $user = User::factory()->admin()->create();
        $group = SecretGroup::factory()->create(['name' => 'Platform']);
        $group->attachTags(['backend'], User::TAG_TYPE);

        $this->actingAs($user)
            ->from(route('secrets.group', $group))
            ->patch(route('secret-groups.tags', $group), [
                'tags' => ['backend', 'infra'],
            ])
            ->assertRedirect(route('secrets.group', $group));

        $group->refresh()->load('tags');

        $this->assertTrue($group->hasTag('backend', User::TAG_TYPE));
        $this->assertTrue($group->hasTag('infra', User::TAG_TYPE));

        $this->actingAs($user)
            ->from(route('secrets.group', $group))
            ->patch(route('secret-groups.tags', $group), [
                'tags' => ['infra'],
            ])
            ->assertRedirect(route('secrets.group', $group));

        $group->refresh()->load('tags');

        $this->assertFalse($group->hasTag('backend', User::TAG_TYPE));
        $this->assertTrue($group->hasTag('infra', User::TAG_TYPE));
    }

    public function test_regular_user_cannot_update_tags_of_inaccessible_group(): void
    {
        $user = User::factory()->create();
        $user->attachTags(['backend'], User::TAG_TYPE);
        $group = SecretGroup::factory()->create(['name' => 'Billing']);
        $group->attachTags(['billing'], User::TAG_TYPE);

        $this->actingAs($user)
            ->patch(route('secret-groups.tags', $group), [
                'tags' => ['backend'],
            ])
            ->assertForbidden();

        $group->refresh()->load('tags');
        $this->assertTrue($group->hasTag('billing', User::TAG_TYPE));
        $this->assertFalse($group->hasTag('backend', User::TAG_TYPE));
    }
}
