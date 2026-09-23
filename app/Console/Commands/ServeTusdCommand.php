<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class ServeTusdCommand extends Command
{
    protected $signature = 'tus:serve {--dump : Print the resolved tusd command and exit}';

    protected $description = 'Run tusd with the same Spaces credentials Laravel uses';

    public function handle(): int
    {
        try {
            $args = $this->argumentsForTusd();
            $env = $this->environment();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('dump')) {
            $this->line('tusd '.implode(' ', $args));

            return self::SUCCESS;
        }

        try {
            $bin = $this->binary();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Starting tusd on '.$env['TUSD_HOST'].':'.$env['TUSD_PORT']);

        $process = new Process([$bin, ...$args], base_path(), $env);
        $process->setTimeout(null);
        $process->setIdleTimeout(null);

        return $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });
    }

    /**
     * @return list<string>
     */
    private function argumentsForTusd(): array
    {
        $disk = config('filesystems.disks.spaces');
        $bucket = $disk['bucket'] ?? null;
        $endpoint = $disk['endpoint'] ?? null;

        if (! $bucket || ! $endpoint) {
            throw new RuntimeException('DO_SPACES_BUCKET and DO_SPACES_ENDPOINT are required to start tusd.');
        }

        $secret = (string) config('tus.hooks_secret');
        if ($secret === '') {
            throw new RuntimeException('TUSD_HOOKS_SECRET is empty.');
        }

        $port = (string) (env('PORT') ?: '80');
        $hookUrl = sprintf(
            'http://%s:%s@127.0.0.1:%s/internal/tus-hooks',
            rawurlencode((string) config('tus.hooks_user')),
            rawurlencode($secret),
            $port,
        );

        $partSize = (string) config('tus.part_size');
        $maxSize = (string) config('tus.max_upload_size');
        $host = (string) config('tus.host');
        $listenPort = (string) config('tus.port');

        return [
            '-host='.$host,
            '-port='.$listenPort,
            '-base-path=/tus/',
            '-behind-proxy',
            '-s3-bucket='.$bucket,
            '-s3-endpoint='.$endpoint,
            '-s3-object-prefix='.config('tus.object_prefix'),
            '-s3-part-size='.$partSize,
            '-s3-min-part-size='.$partSize,
            '-max-size='.$maxSize,
            '-hooks-http='.$hookUrl,
            '-hooks-http-forward-headers=Cookie',
            '-hooks-enabled-events=pre-create,post-finish',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function environment(): array
    {
        $disk = config('filesystems.disks.spaces');
        $env = [];
        foreach (getenv() ?: [] as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $env[$key] = $value;
            }
        }
        foreach ($_ENV as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $env[$key] = $value;
            }
        }

        $env['AWS_ACCESS_KEY_ID'] = (string) ($disk['key'] ?? '');
        $env['AWS_SECRET_ACCESS_KEY'] = (string) ($disk['secret'] ?? '');
        $env['AWS_REGION'] = 'us-east-1';
        $env['TUSD_HOST'] = (string) config('tus.host');
        $env['TUSD_PORT'] = (string) config('tus.port');

        return $env;
    }

    private function binary(): string
    {
        $candidates = array_filter([
            config('tus.bin'),
            '/usr/local/bin/tusd',
            base_path('bin/tusd'),
            '/app/bin/tusd',
        ]);

        foreach ($candidates as $path) {
            if (is_string($path) && is_executable($path)) {
                return $path;
            }
        }

        return $this->downloadBinary();
    }

    private function downloadBinary(): string
    {
        $arch = php_uname('m');
        $tusdArch = str_contains($arch, 'aarch') || str_contains($arch, 'arm64') ? 'arm64' : 'amd64';
        $version = (string) config('tus.version');
        $url = "https://github.com/tus/tusd/releases/download/v{$version}/tusd_linux_{$tusdArch}.tar.gz";

        $dir = base_path('bin');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Unable to create {$dir}");
        }

        $tar = sys_get_temp_dir().'/tusd-'.$tusdArch.'.tar.gz';
        $this->warn("tusd binary missing, downloading {$url}");

        $response = Http::timeout(120)->sink($tar)->get($url);
        if (! $response->successful() || ! is_file($tar)) {
            throw new RuntimeException('Failed to download tusd: HTTP '.$response->status());
        }

        $extract = sys_get_temp_dir().'/tusd-extract-'.bin2hex(random_bytes(4));
        if (! mkdir($extract, 0755, true) && ! is_dir($extract)) {
            throw new RuntimeException("Unable to create {$extract}");
        }

        $tarProc = Process::fromShellCommandline(
            'tar -xzf '.escapeshellarg($tar).' -C '.escapeshellarg($extract),
        );
        $tarProc->run();
        if (! $tarProc->isSuccessful()) {
            try {
                $archive = new \PharData($tar);
                $archive->extractTo($extract, null, true);
            } catch (Throwable $e) {
                throw new RuntimeException('Failed to unpack tusd: '.$tarProc->getErrorOutput().' '.$e->getMessage());
            }
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extract, \FilesystemIterator::SKIP_DOTS),
        );

        $found = null;
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'tusd') {
                $found = $file->getPathname();
                break;
            }
        }

        if ($found === null) {
            throw new RuntimeException('tusd binary not found in archive');
        }

        $target = $dir.'/tusd';
        if (! copy($found, $target)) {
            throw new RuntimeException("Unable to install tusd to {$target}");
        }
        chmod($target, 0755);

        return $target;
    }
}
