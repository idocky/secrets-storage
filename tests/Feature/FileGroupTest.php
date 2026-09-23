<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\FileGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FileGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_file_groups(): void
    {
        $group = FileGroup::factory()->create(['name' => 'Документы']);
        $file = File::factory()->create();

        $this->get(route('file-groups.search'))->assertRedirect(route('login'));
        $this->post(route('file-groups.store'), ['name' => 'Документы'])->assertRedirect(route('login'));
        $this->patch(route('file-groups.tags', $group), ['tags' => ['docs']])->assertRedirect(route('login'));
        $this->get(route('files.group', $group))->assertRedirect(route('login'));
        $this->patch(route('files.group.assign', $file), [
            'file_group_id' => $group->id,
        ])->assertRedirect(route('login'));
    }

    public function test_user_can_create_file_group_from_files_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('files.index'))
            ->post(route('file-groups.store'), [
                'name' => 'Документы',
            ])
            ->assertRedirect(route('files.index'))
            ->assertSessionHas('created_group.name', 'Документы');

        $this->assertDatabaseHas('file_groups', [
            'name' => 'Документы',
        ]);
    }

    public function test_file_group_name_must_be_unique(): void
    {
        $user = User::factory()->create();
        FileGroup::factory()->create(['name' => 'Документы']);

        $this->actingAs($user)
            ->from(route('files.index'))
            ->post(route('file-groups.store'), [
                'name' => 'Документы',
            ])
            ->assertRedirect(route('files.index'))
            ->assertSessionHasErrors('name');
    }

    public function test_user_can_search_file_groups_by_name(): void
    {
        $user = User::factory()->admin()->create();
        FileGroup::factory()->create(['name' => 'Документы']);
        FileGroup::factory()->create(['name' => 'Бэкапы']);
        FileGroup::factory()->create(['name' => 'Медиа']);

        $this->actingAs($user)
            ->getJson(route('file-groups.search', ['q' => 'Меди']))
            ->assertOk()
            ->assertJsonCount(1, 'groups')
            ->assertJsonPath('groups.0.name', 'Медиа')
            ->assertJsonMissingPath('groups.1');
    }

    public function test_search_without_query_returns_groups(): void
    {
        $user = User::factory()->admin()->create();
        FileGroup::factory()->create(['name' => 'Архив']);
        FileGroup::factory()->create(['name' => 'Договоры']);

        $this->actingAs($user)
            ->getJson(route('file-groups.search'))
            ->assertOk()
            ->assertJsonCount(2, 'groups')
            ->assertJsonPath('groups.0.name', 'Архив')
            ->assertJsonPath('groups.1.name', 'Договоры');
    }

    public function test_search_can_load_group_by_id(): void
    {
        $user = User::factory()->admin()->create();
        $group = FileGroup::factory()->create(['name' => 'Отчёты']);
        FileGroup::factory()->create(['name' => 'Other']);

        $this->actingAs($user)
            ->getJson(route('file-groups.search', ['id' => $group->id]))
            ->assertOk()
            ->assertJsonCount(1, 'groups')
            ->assertJsonPath('groups.0.id', $group->id)
            ->assertJsonPath('groups.0.name', 'Отчёты');
    }

    public function test_user_can_delete_file_group(): void
    {
        $user = User::factory()->admin()->create();
        $group = FileGroup::factory()->create(['name' => 'Старое']);
        $file = File::factory()->forGroup($group)->create();

        $this->actingAs($user)
            ->delete(route('file-groups.destroy', $group))
            ->assertRedirect(route('files.index'));

        $this->assertDatabaseMissing('file_groups', ['id' => $group->id]);
        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'file_group_id' => null,
        ]);
    }

    public function test_factory_can_create_group_with_files(): void
    {
        $group = FileGroup::factory()->withFiles(2)->create();

        $this->assertDatabaseCount('files', 2);
        $this->assertCount(2, $group->files);
        $this->assertTrue($group->files->every(fn (File $file) => $file->file_group_id === $group->id));
    }

    public function test_user_can_create_file_group_with_tags(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->from(route('files.index'))
            ->post(route('file-groups.store'), [
                'name' => 'Документы',
                'tags' => ['docs', 'legal'],
            ])
            ->assertRedirect(route('files.index'))
            ->assertSessionHas('created_group.tags', ['docs', 'legal']);

        $group = FileGroup::query()->where('name', 'Документы')->first();

        $this->assertNotNull($group);
        $this->assertTrue($group->hasTag('docs', User::TAG_TYPE));
        $this->assertTrue($group->hasTag('legal', User::TAG_TYPE));
    }

    public function test_search_returns_group_tags_and_hides_inaccessible_groups(): void
    {
        $user = User::factory()->create();
        $user->attachTags(['docs'], User::TAG_TYPE);

        $visible = FileGroup::factory()->create(['name' => 'Документы']);
        $visible->attachTags(['docs'], User::TAG_TYPE);

        $hidden = FileGroup::factory()->create(['name' => 'Финансы']);
        $hidden->attachTags(['finance'], User::TAG_TYPE);

        $this->actingAs($user)
            ->getJson(route('file-groups.search'))
            ->assertOk()
            ->assertJsonCount(1, 'groups')
            ->assertJsonPath('groups.0.name', 'Документы')
            ->assertJsonPath('groups.0.tags', ['docs']);

        $this->actingAs($user)
            ->delete(route('file-groups.destroy', $hidden))
            ->assertForbidden();

        $this->assertDatabaseHas('file_groups', ['id' => $hidden->id]);
    }

    public function test_user_can_update_group_tags_on_group_page(): void
    {
        $user = User::factory()->admin()->create();
        $group = FileGroup::factory()->create(['name' => 'Документы']);
        $group->attachTags(['docs'], User::TAG_TYPE);

        $this->actingAs($user)
            ->from(route('files.group', $group))
            ->patch(route('file-groups.tags', $group), [
                'tags' => ['docs', 'legal'],
            ])
            ->assertRedirect(route('files.group', $group));

        $group->refresh()->load('tags');

        $this->assertTrue($group->hasTag('docs', User::TAG_TYPE));
        $this->assertTrue($group->hasTag('legal', User::TAG_TYPE));

        $this->actingAs($user)
            ->from(route('files.group', $group))
            ->patch(route('file-groups.tags', $group), [
                'tags' => ['legal'],
            ])
            ->assertRedirect(route('files.group', $group));

        $group->refresh()->load('tags');

        $this->assertFalse($group->hasTag('docs', User::TAG_TYPE));
        $this->assertTrue($group->hasTag('legal', User::TAG_TYPE));
    }

    public function test_regular_user_cannot_update_tags_of_inaccessible_group(): void
    {
        $user = User::factory()->create();
        $user->attachTags(['docs'], User::TAG_TYPE);
        $group = FileGroup::factory()->create(['name' => 'Финансы']);
        $group->attachTags(['finance'], User::TAG_TYPE);

        $this->actingAs($user)
            ->patch(route('file-groups.tags', $group), [
                'tags' => ['docs'],
            ])
            ->assertForbidden();

        $group->refresh()->load('tags');
        $this->assertTrue($group->hasTag('finance', User::TAG_TYPE));
        $this->assertFalse($group->hasTag('docs', User::TAG_TYPE));
    }

    public function test_files_index_shows_groups_and_ungrouped_files(): void
    {
        $user = User::factory()->admin()->create();
        $group = FileGroup::factory()->create(['name' => 'Документы']);
        File::factory()->forGroup($group)->create(['original_name' => 'inside.pdf']);
        File::factory()->create(['original_name' => 'loose.txt']);

        $this->actingAs($user)
            ->get(route('files.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Files/Index')
                ->has('groups.data', 1)
                ->where('groups.data.0.name', 'Документы')
                ->where('groups.data.0.files_count', 1)
                ->has('files.data', 1)
                ->where('files.data.0.original_name', 'loose.txt')
                ->where('files.data.0.group', null)
                ->has('availableTags')
                ->where('filters.q', '')
            );
    }

    public function test_files_index_search_finds_groups_and_grouped_files(): void
    {
        $user = User::factory()->admin()->create();
        $backend = FileGroup::factory()->create(['name' => 'Документы']);
        FileGroup::factory()->create(['name' => 'Медиа']);

        File::factory()->forGroup($backend)->create(['original_name' => 'contract.pdf']);
        File::factory()->create(['original_name' => 'notes.txt']);

        $this->actingAs($user)
            ->get(route('files.index', ['q' => 'Документ']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Files/Index')
                ->has('groups.data', 1)
                ->where('groups.data.0.name', 'Документы')
                ->has('files.data', 1)
                ->where('files.data.0.original_name', 'contract.pdf')
                ->where('files.data.0.group.id', $backend->id)
            );
    }

    public function test_user_can_view_files_inside_group(): void
    {
        $user = User::factory()->admin()->create();
        $group = FileGroup::factory()->create(['name' => 'Документы']);
        $other = FileGroup::factory()->create(['name' => 'Медиа']);

        File::factory()->forGroup($group)->create(['original_name' => 'a.pdf']);
        File::factory()->forGroup($other)->create(['original_name' => 'b.png']);
        File::factory()->create(['original_name' => 'c.txt']);

        $this->actingAs($user)
            ->get(route('files.group', $group))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Files/Group')
                ->where('group.id', $group->id)
                ->where('group.name', 'Документы')
                ->has('availableTags')
                ->has('files.data', 1)
                ->where('files.data.0.original_name', 'a.pdf')
            );
    }

    public function test_upload_form_can_preselect_group(): void
    {
        $user = User::factory()->admin()->create();
        $group = FileGroup::factory()->create(['name' => 'Документы']);
        $group->attachTags(['docs'], User::TAG_TYPE);

        $this->actingAs($user)
            ->get(route('files.upload', ['group' => $group->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Files/Upload')
                ->where('initialGroup.id', $group->id)
                ->where('initialGroup.name', 'Документы')
                ->where('initialGroup.tags', ['docs'])
                ->has('availableTags')
            );
    }

    public function test_regular_user_cannot_open_upload_form_for_inaccessible_group(): void
    {
        $user = User::factory()->create();
        $user->attachTags(['docs'], User::TAG_TYPE);
        $group = FileGroup::factory()->create(['name' => 'Финансы']);
        $group->attachTags(['finance'], User::TAG_TYPE);

        $this->actingAs($user)
            ->get(route('files.upload', ['group' => $group->id]))
            ->assertForbidden();
    }

    public function test_regular_user_sees_only_files_and_groups_with_matching_tags(): void
    {
        $user = User::factory()->create();
        $user->attachTags(['docs'], User::TAG_TYPE);

        $visibleGroup = FileGroup::factory()->create(['name' => 'Документы']);
        $visibleGroup->attachTags(['docs'], User::TAG_TYPE);

        $hiddenGroup = FileGroup::factory()->create(['name' => 'Финансы']);
        $hiddenGroup->attachTags(['finance'], User::TAG_TYPE);

        $visible = File::factory()->create(['original_name' => 'visible.pdf']);
        $visible->attachTags(['docs'], User::TAG_TYPE);

        $hidden = File::factory()->create(['original_name' => 'hidden.pdf']);
        $hidden->attachTags(['finance'], User::TAG_TYPE);

        File::factory()->create(['original_name' => 'untagged.txt']);

        $this->actingAs($user)
            ->get(route('files.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Files/Index')
                ->has('groups.data', 1)
                ->where('groups.data.0.name', 'Документы')
                ->has('files.data', 1)
                ->where('files.data.0.original_name', 'visible.pdf')
            );

        $this->actingAs($user)->get(route('files.group', $hiddenGroup))->assertForbidden();
        $this->actingAs($user)->delete(route('files.destroy', $hidden))->assertForbidden();
        $this->actingAs($user)
            ->patch(route('files.public', $hidden), ['is_public' => true])
            ->assertForbidden();

        $this->assertDatabaseHas('files', ['id' => $hidden->id, 'is_public' => false]);
    }

    public function test_user_can_move_file_into_group(): void
    {
        $user = User::factory()->admin()->create();
        $group = FileGroup::factory()->create(['name' => 'Документы']);
        $file = File::factory()->create(['original_name' => 'notes.txt']);

        $this->actingAs($user)
            ->from(route('files.index'))
            ->patch(route('files.group.assign', $file), [
                'file_group_id' => $group->id,
            ])
            ->assertRedirect(route('files.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'file_group_id' => $group->id,
        ]);
    }

    public function test_user_can_move_file_between_groups(): void
    {
        $user = User::factory()->admin()->create();
        $from = FileGroup::factory()->create(['name' => 'Черновики']);
        $to = FileGroup::factory()->create(['name' => 'Документы']);
        $file = File::factory()->forGroup($from)->create(['original_name' => 'draft.txt']);

        $this->actingAs($user)
            ->from(route('files.index'))
            ->patch(route('files.group.assign', $file), [
                'file_group_id' => $to->id,
            ])
            ->assertRedirect(route('files.index'));

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'file_group_id' => $to->id,
        ]);
    }

    public function test_moving_file_into_its_current_group_is_noop(): void
    {
        $user = User::factory()->admin()->create();
        $group = FileGroup::factory()->create(['name' => 'Документы']);
        $file = File::factory()->forGroup($group)->create();

        $this->actingAs($user)
            ->from(route('files.index'))
            ->patch(route('files.group.assign', $file), [
                'file_group_id' => $group->id,
            ])
            ->assertRedirect(route('files.index'))
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'file_group_id' => $group->id,
        ]);
    }

    public function test_move_file_requires_existing_group(): void
    {
        $user = User::factory()->admin()->create();
        $file = File::factory()->create();

        $this->actingAs($user)
            ->from(route('files.index'))
            ->patch(route('files.group.assign', $file), [
                'file_group_id' => 999,
            ])
            ->assertRedirect(route('files.index'))
            ->assertSessionHasErrors('file_group_id');

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'file_group_id' => null,
        ]);
    }

    public function test_regular_user_can_move_visible_file_into_visible_group(): void
    {
        $user = User::factory()->create();
        $user->attachTags(['docs'], User::TAG_TYPE);

        $group = FileGroup::factory()->create(['name' => 'Документы']);
        $group->attachTags(['docs'], User::TAG_TYPE);

        $file = File::factory()->create(['original_name' => 'visible.pdf']);
        $file->attachTags(['docs'], User::TAG_TYPE);

        $this->actingAs($user)
            ->from(route('files.index'))
            ->patch(route('files.group.assign', $file), [
                'file_group_id' => $group->id,
            ])
            ->assertRedirect(route('files.index'));

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'file_group_id' => $group->id,
        ]);
    }

    public function test_regular_user_cannot_move_inaccessible_file_into_group(): void
    {
        $user = User::factory()->create();
        $user->attachTags(['docs'], User::TAG_TYPE);

        $group = FileGroup::factory()->create(['name' => 'Документы']);
        $group->attachTags(['docs'], User::TAG_TYPE);

        $file = File::factory()->create(['original_name' => 'hidden.pdf']);
        $file->attachTags(['finance'], User::TAG_TYPE);

        $this->actingAs($user)
            ->patch(route('files.group.assign', $file), [
                'file_group_id' => $group->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'file_group_id' => null,
        ]);
    }

    public function test_regular_user_cannot_move_file_into_inaccessible_group(): void
    {
        $user = User::factory()->create();
        $user->attachTags(['docs'], User::TAG_TYPE);

        $group = FileGroup::factory()->create(['name' => 'Финансы']);
        $group->attachTags(['finance'], User::TAG_TYPE);

        $file = File::factory()->create(['original_name' => 'visible.pdf']);
        $file->attachTags(['docs'], User::TAG_TYPE);

        $this->actingAs($user)
            ->patch(route('files.group.assign', $file), [
                'file_group_id' => $group->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'file_group_id' => null,
        ]);
    }
}
