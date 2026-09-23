<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FilePublicDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_when_downloading_private_file(): void
    {
        $file = File::create([
            'original_name' => 'secret.pdf',
            'storage_name' => 'abc123.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'is_public' => false,
        ]);

        $this->get(route('files.download', $file))
            ->assertRedirect(route('login'));
    }

    public function test_guest_can_download_public_file(): void
    {
        Storage::fake('spaces');
        Storage::disk('spaces')->put('tus-uploads/public-file.bin', 'hello-public');

        $file = File::create([
            'original_name' => 'public.bin',
            'storage_name' => 'public-file.bin',
            'mime_type' => 'application/octet-stream',
            'size' => 12,
            'is_public' => true,
        ]);

        $this->get(route('files.download', $file))
            ->assertOk();
    }

    public function test_authenticated_user_can_download_private_file(): void
    {
        Storage::fake('spaces');
        Storage::disk('spaces')->put('tus-uploads/private-file.bin', 'hello-private');

        $user = User::factory()->admin()->create();
        $file = File::create([
            'original_name' => 'private.bin',
            'storage_name' => 'private-file.bin',
            'mime_type' => 'application/octet-stream',
            'size' => 13,
            'is_public' => false,
        ]);

        $this->actingAs($user)
            ->get(route('files.download', $file))
            ->assertOk();
    }

    public function test_regular_user_cannot_download_inaccessible_private_file(): void
    {
        Storage::fake('spaces');
        Storage::disk('spaces')->put('tus-uploads/hidden.bin', 'secret');

        $user = User::factory()->create();
        $user->attachTags(['docs'], User::TAG_TYPE);

        $file = File::factory()->create([
            'original_name' => 'hidden.bin',
            'storage_name' => 'hidden.bin',
            'is_public' => false,
        ]);
        $file->attachTags(['finance'], User::TAG_TYPE);

        $this->actingAs($user)
            ->get(route('files.download', $file))
            ->assertForbidden();
    }

    public function test_regular_user_can_download_private_file_with_matching_tags(): void
    {
        Storage::fake('spaces');
        Storage::disk('spaces')->put('tus-uploads/visible.bin', 'ok');

        $user = User::factory()->create();
        $user->attachTags(['docs'], User::TAG_TYPE);

        $file = File::factory()->create([
            'original_name' => 'visible.bin',
            'storage_name' => 'visible.bin',
            'is_public' => false,
        ]);
        $file->attachTags(['docs'], User::TAG_TYPE);

        $this->actingAs($user)
            ->get(route('files.download', $file))
            ->assertOk();
    }

    public function test_guest_can_download_public_file_even_if_tagged(): void
    {
        Storage::fake('spaces');
        Storage::disk('spaces')->put('tus-uploads/tagged-public.bin', 'hello-public');

        $file = File::factory()->public()->create([
            'original_name' => 'tagged-public.bin',
            'storage_name' => 'tagged-public.bin',
        ]);
        $file->attachTags(['docs'], User::TAG_TYPE);

        $this->get(route('files.download', $file))
            ->assertOk();
    }

    public function test_user_can_toggle_file_public_flag(): void
    {
        $user = User::factory()->admin()->create();
        $file = File::create([
            'original_name' => 'doc.txt',
            'storage_name' => 'doc.txt',
            'mime_type' => 'text/plain',
            'size' => 1,
            'is_public' => false,
        ]);

        $this->actingAs($user)
            ->patch(route('files.public', $file), ['is_public' => true])
            ->assertRedirect();

        $this->assertTrue($file->fresh()->is_public);

        $this->actingAs($user)
            ->patch(route('files.public', $file), ['is_public' => false])
            ->assertRedirect();

        $this->assertFalse($file->fresh()->is_public);
    }

    public function test_files_index_includes_is_public(): void
    {
        $user = User::factory()->admin()->create();
        File::create([
            'original_name' => 'a.txt',
            'storage_name' => 'a.txt',
            'mime_type' => 'text/plain',
            'size' => 1,
            'is_public' => true,
        ]);

        $this->actingAs($user)
            ->get(route('files.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Files/Index')
                ->where('files.data.0.is_public', true)
                ->has('files.data.0.download_url')
                ->has('files.data.0.tags')
                ->where('files.data.0.group', null)
            );
    }
}
