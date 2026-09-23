<?php

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Throwable;

class EnsureTusMultipartLifecycleCommand extends Command
{
    protected $signature = 'tus:ensure-lifecycle';

    protected $description = 'Ensure Spaces lifecycle aborts incomplete TUS multipart uploads';

    public function handle(): int
    {
        $disk = config('filesystems.disks.spaces');
        $bucket = $disk['bucket'] ?? null;
        $key = $disk['key'] ?? null;
        $secret = $disk['secret'] ?? null;

        if (! $bucket || ! $key || ! $secret) {
            $this->warn('Spaces credentials are not configured; skipping lifecycle setup.');

            return self::SUCCESS;
        }

        $prefix = (string) config('tus.object_prefix');
        $days = max(1, (int) config('tus.lifecycle_days'));
        $ruleId = 'abort-incomplete-tus-multipart';

        $client = new S3Client([
            'version' => 'latest',
            'region' => $disk['region'] ?: 'us-east-1',
            'endpoint' => $disk['endpoint'] ?? null,
            'use_path_style_endpoint' => false,
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ]);

        try {
            $rules = [];
            try {
                $existing = $client->getBucketLifecycleConfiguration([
                    'Bucket' => $bucket,
                ]);
                $rules = $existing['Rules'] ?? [];
            } catch (Throwable) {
                $rules = [];
            }

            $rules = array_values(array_filter(
                $rules,
                static fn ($rule) => ($rule['ID'] ?? null) !== $ruleId,
            ));

            $rules[] = [
                'ID' => $ruleId,
                'Filter' => ['Prefix' => $prefix],
                'Status' => 'Enabled',
                'AbortIncompleteMultipartUpload' => [
                    'DaysAfterInitiation' => $days,
                ],
            ];

            $client->putBucketLifecycleConfiguration([
                'Bucket' => $bucket,
                'LifecycleConfiguration' => [
                    'Rules' => $rules,
                ],
            ]);
        } catch (Throwable $e) {
            $this->warn('Could not update Spaces lifecycle: '.$e->getMessage());

            return self::SUCCESS;
        }

        $this->info("Lifecycle rule {$ruleId} set to abort incomplete multipart uploads after {$days} day(s).");

        return self::SUCCESS;
    }
}
