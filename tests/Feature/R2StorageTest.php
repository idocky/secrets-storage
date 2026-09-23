<?php

namespace Tests\Feature;

use Aws\Middleware;
use Aws\Result;
use GuzzleHttp\Promise\FulfilledPromise;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

class R2StorageTest extends TestCase
{
    public function test_r2_put_omits_acl_header(): void
    {
        $request = $this->capturePut('https://example.r2.cloudflarestorage.com', 'auto');

        $this->assertFalse($request->hasHeader('x-amz-acl'));
    }

    public function test_spaces_put_keeps_private_acl(): void
    {
        $request = $this->capturePut('https://fra1.digitaloceanspaces.com', 'fra1');

        $this->assertSame('private', $request->getHeaderLine('x-amz-acl'));
    }

    private function capturePut(string $endpoint, string $region): RequestInterface
    {
        config([
            'filesystems.disks.spaces' => [
                'driver' => 's3',
                'key' => 'key',
                'secret' => 'secret',
                'region' => $region,
                'bucket' => 'bucket',
                'endpoint' => $endpoint,
                'use_path_style_endpoint' => false,
            ],
        ]);

        Storage::purge('spaces');

        $client = Storage::disk('spaces')->getClient();
        $captured = null;

        $client->getHandlerList()->appendSign(
            Middleware::tap(function ($command, ?RequestInterface $request) use (&$captured) {
                $captured = $request;
            })
        );
        $client->getHandlerList()->setHandler(fn () => new FulfilledPromise(new Result([])));

        $client->execute($client->getCommand('PutObject', [
            'Bucket' => 'bucket',
            'Key' => 'secret-files/a.txt',
            'Body' => 'hello',
            'ACL' => 'private',
        ]));

        $this->assertInstanceOf(RequestInterface::class, $captured);

        return $captured;
    }
}
