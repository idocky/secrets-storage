<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\FileGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TusdHookTest extends TestCase
{
    use RefreshDatabase;

    private const STORAGE_NAME = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.bin';

    public function test_hook_rejects_missing_secret(): void
    {
        $this->postJson(route('tus.hooks'), $this->preCreatePayload())
            ->assertUnauthorized();
    }

    public function test_hook_rejects_unauthenticated_upload(): void
    {
        $this->postTusdHook($this->preCreatePayload())
            ->assertOk()
            ->assertJson([
                'RejectUpload' => true,
                'HTTPResponse' => [
                    'StatusCode' => 401,
                ],
            ]);
    }

    public function test_pre_create_accepts_authenticated_upload_and_sets_storage_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postTusdHook($this->preCreatePayload())
            ->assertOk()
            ->assertJson([
                'ChangeFileInfo' => [
                    'ID' => self::STORAGE_NAME,
                ],
            ]);
    }

    public function test_pre_create_rejects_file_over_limit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postTusdHook($this->preCreatePayload(size: 5 * 1024 * 1024 * 1024 + 1))
            ->assertOk()
            ->assertJson([
                'RejectUpload' => true,
                'HTTPResponse' => [
                    'StatusCode' => 413,
                ],
            ]);
    }

    public function test_pre_create_rejects_path_traversal_filename(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postTusdHook($this->preCreatePayload(filename: '../etc/passwd'))
            ->assertOk()
            ->assertJson([
                'RejectUpload' => true,
                'HTTPResponse' => [
                    'StatusCode' => 400,
                ],
            ]);
    }

    public function test_post_finish_creates_file_record(): void
    {
        $this->postTusdHook($this->postFinishPayload())
            ->assertOk();

        $this->assertDatabaseHas('files', [
            'storage_name' => self::STORAGE_NAME,
            'original_name' => 'video.mp4',
            'mime_type' => 'video/mp4',
            'size' => 1024,
        ]);
    }

    public function test_post_finish_is_idempotent(): void
    {
        $this->postTusdHook($this->postFinishPayload())->assertOk();
        $this->postTusdHook($this->postFinishPayload())->assertOk();

        $this->assertSame(1, File::query()->where('storage_name', self::STORAGE_NAME)->count());
    }

    public function test_tus_serve_dump_uses_spaces_config(): void
    {
        config([
            'filesystems.disks.spaces.bucket' => 'my-bucket',
            'filesystems.disks.spaces.endpoint' => 'https://fra1.digitaloceanspaces.com',
            'filesystems.disks.spaces.key' => 'key',
            'filesystems.disks.spaces.secret' => 'secret',
            'tus.hooks_secret' => 'testing-secret',
        ]);

        $this->artisan('tus:serve', ['--dump' => true])
            ->expectsOutputToContain('-s3-bucket=my-bucket')
            ->assertSuccessful();
    }

    public function test_upload_page_points_at_tusd_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('files.upload'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Files/Upload')
                ->where('tusEndpoint', rtrim(url('/tus'), '/').'/')
                ->has('availableTags')
                ->where('initialGroup', null)
            );
    }

    public function test_pre_create_rejects_inaccessible_group(): void
    {
        $user = User::factory()->create();
        $user->attachTags(['docs'], User::TAG_TYPE);
        $group = FileGroup::factory()->create(['name' => 'Финансы']);
        $group->attachTags(['finance'], User::TAG_TYPE);

        $this->actingAs($user)
            ->postTusdHook($this->preCreatePayload(groupId: $group->id))
            ->assertOk()
            ->assertJson([
                'RejectUpload' => true,
                'HTTPResponse' => [
                    'StatusCode' => 400,
                ],
            ]);
    }

    public function test_post_finish_attaches_group_and_tags(): void
    {
        $group = FileGroup::factory()->create(['name' => 'Документы']);

        $this->postTusdHook($this->postFinishPayload(
            groupId: $group->id,
            tags: ['docs', 'legal'],
        ))->assertOk();

        $file = File::query()->where('storage_name', self::STORAGE_NAME)->first();

        $this->assertNotNull($file);
        $this->assertSame($group->id, $file->file_group_id);
        $this->assertTrue($file->hasTag('docs', User::TAG_TYPE));
        $this->assertTrue($file->hasTag('legal', User::TAG_TYPE));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postTusdHook(array $payload)
    {
        return $this->withHeaders([
            'X-Tusd-Secret' => 'testing-secret',
            'Accept' => 'application/json',
        ])->postJson(route('tus.hooks'), $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function preCreatePayload(?int $size = 1024, string $filename = self::STORAGE_NAME, ?int $groupId = null): array
    {
        $metadata = [
            'filename' => $filename,
            'filetype' => 'video/mp4',
            'originalname' => 'video.mp4',
        ];

        if ($groupId !== null) {
            $metadata['groupid'] = (string) $groupId;
        }

        return [
            'Type' => 'pre-create',
            'Event' => [
                'Upload' => [
                    'Size' => $size,
                    'MetaData' => $metadata,
                ],
            ],
        ];
    }

    /**
     * @param  list<string>  $tags
     * @return array<string, mixed>
     */
    private function postFinishPayload(?int $groupId = null, array $tags = []): array
    {
        $metadata = [
            'filename' => self::STORAGE_NAME,
            'filetype' => 'video/mp4',
            'originalname' => 'video.mp4',
        ];

        if ($groupId !== null) {
            $metadata['groupid'] = (string) $groupId;
        }

        if ($tags !== []) {
            $metadata['tags'] = json_encode($tags);
        }

        return [
            'Type' => 'post-finish',
            'Event' => [
                'Upload' => [
                    'Size' => 1024,
                    'MetaData' => $metadata,
                    'Storage' => [
                        'Type' => 's3store',
                        'Bucket' => 'test-bucket',
                        'Key' => 'tus-uploads/'.self::STORAGE_NAME,
                    ],
                ],
            ],
        ];
    }
}
