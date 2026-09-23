<?php

namespace Tests\Feature;

use App\Models\Secret;
use App\Models\SecretFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SecretFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_secret_with_files_and_without_value(): void
    {
        Storage::fake('spaces');
        $user = User::factory()->create();

        $cert = UploadedFile::fake()->create('tls.pem', 12, 'application/x-pem-file');
        $key = UploadedFile::fake()->create('tls.key', 8, 'application/octet-stream');

        $this->actingAs($user)
            ->post(route('secrets.store'), [
                'type' => 'file',
                'key' => 'TLS_CERT',
                'environment' => ['production'],
                'files' => [$cert, $key],
            ])
            ->assertRedirect(route('secrets.index'));

        $secret = Secret::query()->where('key', 'TLS_CERT')->first();

        $this->assertNotNull($secret);
        $this->assertSame('file', $secret->type->value);
        $this->assertNull($secret->value);
        $this->assertNull($secret->description);
        $this->assertCount(2, $secret->files);
        $this->assertEqualsCanonicalizing(
            ['tls.pem', 'tls.key'],
            $secret->files->pluck('original_name')->all(),
        );

        foreach ($secret->files as $file) {
            Storage::disk('spaces')->assertExists($file->storagePath());
        }
    }

    public function test_user_cannot_create_file_secret_with_value(): void
    {
        Storage::fake('spaces');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('secrets.create'))
            ->post(route('secrets.store'), [
                'type' => 'file',
                'key' => 'APP_BUNDLE',
                'value' => 'passphrase',
                'environment' => ['staging'],
                'files' => [
                    UploadedFile::fake()->create('bundle.p12', 20, 'application/x-pkcs12'),
                ],
            ])
            ->assertRedirect(route('secrets.create'))
            ->assertSessionHasErrors('value');

        $this->assertDatabaseMissing('secrets', ['key' => 'APP_BUNDLE']);
    }

    public function test_password_secret_cannot_include_files(): void
    {
        Storage::fake('spaces');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('secrets.create'))
            ->post(route('secrets.store'), [
                'type' => 'password',
                'key' => 'APP_BUNDLE',
                'value' => 'passphrase',
                'environment' => ['staging'],
                'files' => [
                    UploadedFile::fake()->create('bundle.p12', 20, 'application/x-pkcs12'),
                ],
            ])
            ->assertRedirect(route('secrets.create'))
            ->assertSessionHasErrors('files');
    }

    public function test_password_create_requires_value(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('secrets.create'))
            ->post(route('secrets.store'), [
                'type' => 'password',
                'key' => 'EMPTY',
                'environment' => ['dev'],
            ])
            ->assertRedirect(route('secrets.create'))
            ->assertSessionHasErrors('value');
    }

    public function test_file_create_requires_files(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('secrets.create'))
            ->post(route('secrets.store'), [
                'type' => 'file',
                'key' => 'EMPTY_FILE',
                'environment' => ['dev'],
            ])
            ->assertRedirect(route('secrets.create'))
            ->assertSessionHasErrors('files');
    }

    public function test_secrets_index_includes_files_and_hides_value(): void
    {
        Storage::fake('spaces');
        $user = User::factory()->admin()->create();

        $secret = Secret::factory()->file()->create(['key' => 'ONLY_FILES']);
        $file = SecretFile::storeForSecret(
            $secret,
            UploadedFile::fake()->create('id_rsa', 4, 'application/octet-stream'),
        );

        $this->actingAs($user)
            ->get(route('secrets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Secrets/Index')
                ->has('secrets.data', 1)
                ->where('secrets.data.0.key', 'ONLY_FILES')
                ->where('secrets.data.0.type', 'file')
                ->where('secrets.data.0.type_label', 'File')
                ->where('secrets.data.0.has_value', false)
                ->where('secrets.data.0.description', null)
                ->missing('secrets.data.0.value')
                ->has('secrets.data.0.files', 1)
                ->where('secrets.data.0.files.0.original_name', 'id_rsa')
                ->where('secrets.data.0.files.0.download_url', route('secrets.files.download', [$secret, $file]))
            );
    }

    public function test_user_can_download_secret_file(): void
    {
        Storage::fake('spaces');
        $user = User::factory()->admin()->create();
        $secret = Secret::factory()->file()->create();
        $file = SecretFile::storeForSecret(
            $secret,
            UploadedFile::fake()->create('cert.pem', 6, 'application/x-pem-file'),
        );

        $this->actingAs($user)
            ->get(route('secrets.files.download', [$secret, $file]))
            ->assertOk()
            ->assertDownload('cert.pem');
    }

    public function test_guest_cannot_download_secret_file(): void
    {
        Storage::fake('spaces');
        $secret = Secret::factory()->file()->create();
        $file = SecretFile::storeForSecret(
            $secret,
            UploadedFile::fake()->create('cert.pem', 6, 'application/x-pem-file'),
        );

        $this->get(route('secrets.files.download', [$secret, $file]))
            ->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_download_inaccessible_secret_file(): void
    {
        Storage::fake('spaces');

        $user = User::factory()->create();
        $user->attachTags(['backend'], User::TAG_TYPE);

        $secret = Secret::factory()->file()->create();
        $secret->attachTags(['billing'], User::TAG_TYPE);
        $file = SecretFile::storeForSecret(
            $secret,
            UploadedFile::fake()->create('secret.bin', 3, 'application/octet-stream'),
        );

        $this->actingAs($user)
            ->get(route('secrets.files.download', [$secret, $file]))
            ->assertForbidden();
    }

    public function test_download_returns_not_found_when_file_does_not_belong_to_secret(): void
    {
        Storage::fake('spaces');
        $user = User::factory()->admin()->create();

        $secret = Secret::factory()->file()->create();
        $other = Secret::factory()->file()->create();
        $file = SecretFile::storeForSecret(
            $other,
            UploadedFile::fake()->create('other.pem', 3, 'application/x-pem-file'),
        );

        $this->actingAs($user)
            ->get(route('secrets.files.download', [$secret, $file]))
            ->assertNotFound();
    }

    public function test_deleting_secret_removes_files_from_spaces(): void
    {
        Storage::fake('spaces');
        $user = User::factory()->admin()->create();
        $secret = Secret::factory()->file()->create();
        $file = SecretFile::storeForSecret(
            $secret,
            UploadedFile::fake()->create('gone.txt', 2, 'text/plain'),
        );
        $second = SecretFile::storeForSecret(
            $secret,
            UploadedFile::fake()->create('also-gone.pem', 4, 'application/x-pem-file'),
        );
        $paths = [$file->storagePath(), $second->storagePath()];

        foreach ($paths as $path) {
            Storage::disk('spaces')->assertExists($path);
        }

        $this->actingAs($user)
            ->delete(route('secrets.destroy', $secret))
            ->assertRedirect();

        $this->assertDatabaseMissing('secrets', ['id' => $secret->id]);
        $this->assertDatabaseMissing('secret_files', ['id' => $file->id]);
        $this->assertDatabaseMissing('secret_files', ['id' => $second->id]);

        foreach ($paths as $path) {
            Storage::disk('spaces')->assertMissing($path);
        }
    }

    public function test_deleting_secret_model_directly_still_removes_files_from_spaces(): void
    {
        Storage::fake('spaces');
        $secret = Secret::factory()->file()->create();
        $file = SecretFile::storeForSecret(
            $secret,
            UploadedFile::fake()->create('direct.txt', 2, 'text/plain'),
        );
        $path = $file->storagePath();

        $secret->delete();

        $this->assertDatabaseMissing('secrets', ['id' => $secret->id]);
        $this->assertDatabaseMissing('secret_files', ['id' => $file->id]);
        Storage::disk('spaces')->assertMissing($path);
    }

    public function test_secret_is_kept_if_spaces_delete_fails(): void
    {
        Storage::fake('spaces');
        $user = User::factory()->admin()->create();
        $secret = Secret::factory()->file()->create();
        $file = SecretFile::storeForSecret(
            $secret,
            UploadedFile::fake()->create('kept.txt', 2, 'text/plain'),
        );

        Storage::shouldReceive('disk')
            ->with('spaces')
            ->andReturnSelf();
        Storage::shouldReceive('delete')
            ->once()
            ->with($file->storagePath())
            ->andReturn(false);

        $this->actingAs($user)
            ->from(route('secrets.index'))
            ->delete(route('secrets.destroy', $secret))
            ->assertRedirect(route('secrets.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('secrets', ['id' => $secret->id]);
        $this->assertDatabaseHas('secret_files', ['id' => $file->id]);
    }

    public function test_reveal_returns_null_when_secret_has_no_value(): void
    {
        $user = User::factory()->admin()->create();
        $secret = Secret::factory()->file()->create();

        $this->actingAs($user)
            ->get(route('secrets.reveal', $secret))
            ->assertOk()
            ->assertJson(['value' => null]);
    }
}
