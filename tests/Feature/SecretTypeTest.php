<?php

namespace Tests\Feature;

use App\Enums\SecretType;
use App\Models\Secret;
use App\Models\SecretFile;
use App\Models\User;
use App\Secrets\Contracts\SecretTypeStrategy;
use App\Secrets\SecretTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SecretTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_secret_type_has_a_registered_strategy(): void
    {
        $registry = $this->app->make(SecretTypeRegistry::class);

        foreach (SecretType::cases() as $type) {
            $this->assertInstanceOf(SecretTypeStrategy::class, $registry->get($type));
        }

        $contracts = $registry->contracts();

        $this->assertSame(['password', 'file', 'note'], array_column($contracts, 'slug'));
        $this->assertSame(
            ['Password / API key', 'File', 'Secure Note'],
            array_column($contracts, 'label'),
        );
    }

    public function test_user_can_create_secure_note(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('secrets.store'), [
                'type' => 'note',
                'key' => 'VPN_INSTRUCTIONS',
                'value' => "host: vpn.example\nuser: admin",
                'environment' => ['production'],
            ])
            ->assertRedirect(route('secrets.index'));

        $secret = Secret::query()->where('key', 'VPN_INSTRUCTIONS')->first();

        $this->assertNotNull($secret);
        $this->assertSame(SecretType::Note, $secret->type);
        $this->assertSame("host: vpn.example\nuser: admin", $secret->value);
        $this->assertNotSame("host: vpn.example\nuser: admin", $secret->getRawOriginal('value'));
        $this->assertNull($secret->description);
        $this->assertCount(0, $secret->files);
    }

    public function test_user_can_create_file_secret_with_plaintext_description(): void
    {
        Storage::fake('spaces');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('secrets.store'), [
                'type' => 'file',
                'key' => 'TLS_BUNDLE',
                'description' => 'Сертификат для nginx на проде',
                'environment' => ['production'],
                'files' => [
                    UploadedFile::fake()->create('fullchain.pem', 10, 'application/x-pem-file'),
                ],
            ])
            ->assertRedirect(route('secrets.index'));

        $secret = Secret::query()->where('key', 'TLS_BUNDLE')->first();

        $this->assertNotNull($secret);
        $this->assertSame(SecretType::File, $secret->type);
        $this->assertNull($secret->value);
        $this->assertSame('Сертификат для nginx на проде', $secret->description);
        $this->assertSame('Сертификат для nginx на проде', $secret->getRawOriginal('description'));
        $this->assertCount(1, $secret->files);
    }

    public function test_index_shows_file_description_and_legacy_value_without_exposing_secret(): void
    {
        Storage::fake('spaces');
        $user = User::factory()->admin()->create();

        $secret = Secret::factory()->file()->create([
            'key' => 'LEGACY_MIXED',
            'value' => 'still-a-secret',
            'description' => 'контекст к файлам',
        ]);
        SecretFile::storeForSecret(
            $secret,
            UploadedFile::fake()->create('key.pem', 4, 'application/x-pem-file'),
        );

        $this->actingAs($user)
            ->get(route('secrets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Secrets/Index')
                ->has('secrets.data', 1)
                ->where('secrets.data.0.key', 'LEGACY_MIXED')
                ->where('secrets.data.0.type', 'file')
                ->where('secrets.data.0.description', 'контекст к файлам')
                ->where('secrets.data.0.has_value', true)
                ->missing('secrets.data.0.value')
                ->has('secrets.data.0.files', 1)
            );
    }

    public function test_note_value_is_revealed_like_password(): void
    {
        $user = User::factory()->admin()->create();
        $secret = Secret::factory()->note()->create([
            'key' => 'NOTE',
            'value' => 'hidden-note',
        ]);

        $this->actingAs($user)
            ->get(route('secrets.reveal', $secret))
            ->assertOk()
            ->assertJson(['value' => 'hidden-note']);
    }

    public function test_unknown_type_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('secrets.create'))
            ->post(route('secrets.store'), [
                'type' => 'totp',
                'key' => 'UNKNOWN',
                'value' => 'secret',
                'environment' => ['dev'],
            ])
            ->assertRedirect(route('secrets.create'))
            ->assertSessionHasErrors('type');
    }

    public function test_note_cannot_include_files(): void
    {
        Storage::fake('spaces');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('secrets.create'))
            ->post(route('secrets.store'), [
                'type' => 'note',
                'key' => 'NOTE_WITH_FILE',
                'value' => 'text',
                'environment' => ['dev'],
                'files' => [
                    UploadedFile::fake()->create('note.txt', 1, 'text/plain'),
                ],
            ])
            ->assertRedirect(route('secrets.create'))
            ->assertSessionHasErrors('files');
    }

    public function test_create_form_exposes_strategy_contracts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('secrets.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Secrets/Create')
                ->where('defaultType', 'password')
                ->has('secretTypes', 3)
                ->where('secretTypes.0.slug', 'password')
                ->where('secretTypes.0.fields.0.widget', 'password')
                ->where('secretTypes.1.slug', 'file')
                ->where('secretTypes.1.fields.0.name', 'files')
                ->where('secretTypes.1.fields.1.name', 'description')
                ->where('secretTypes.2.slug', 'note')
                ->where('secretTypes.2.fields.0.widget', 'textarea')
            );
    }
}
