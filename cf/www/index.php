<?php

include __DIR__ . "/../vendor/autoload.php";

use Symfony\Component\HttpFoundation\Request;
use Silex\Application;
use Aws\S3\S3Client;

require 'vendor/autoload.php';

$app = new Application([
    'debug'        => false,
    'aws.config'   => [
        'debug'       => false,
        'version'     => 'latest',
        'region'      => 'eu-west-1',
        'credentials' => [
            'key'    => $_ENV['s3key'],
            'secret' => $_ENV['s3secret'],
        ],
    ],
]);

$app['aws'] = function () use ($app) {
    return new S3Client($app['aws.config']);
};

$app->post('/upload', function (Request $request, Application $app) {
    $metadata = json_decode($request->get('metadata'), true);
    $token    = $request->get('token');

    if ($token === 'mySuperSecretToken') {
        $fileName = $_FILES['file']['name'];
        $fileType = $_FILES['file']['type'];
        $tmpName  = $_FILES['file']['tmp_name'];

        /** @var \Aws\S3\S3Client $s3 */
        $s3 = $app['aws'];
        try {
            $key = date('YmdHis') . "_" . $fileName;
            $s3->putObject([
                'Bucket'      => 'myBucket',
                'Key'         => $key,
                'SourceFile'  => $tmpName,
                'ContentType' => $fileType,
                'Metadata'    => $metadata,
            ]);
            unlink($tmpName);

            return $app->json([
                'status' => true,
                'key'    => $key,
            ]);
        } catch (Aws\S3\Exception\S3Exception $e) {
            return $app->json([
                'status' => false,
                'error'  => $e->getMessage(),
            ]);
        }
    } else {
        return $app->json([
            'status' => false,
            'error'  => "Token error",
        ]);
    }
});

$app->run();