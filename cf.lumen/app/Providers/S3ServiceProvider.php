<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Aws\S3\S3Client;

class S3ServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(S3Client::class, function ($app) {
            $conf = [
                'debug'       => false,
                'version'     => getenv('AWS_VERSION'),
                'region'      => getenv('AWS_REGION'),
                'credentials' => [
                    'key'    => getenv('s3key'),
                    'secret' => getenv('s3secret'),
                ],
            ];

            return new S3Client($conf);
        });
    }
}
