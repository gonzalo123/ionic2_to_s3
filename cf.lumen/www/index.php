<?php

include __DIR__ . "/../vendor/autoload.php";

use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Laravel\Lumen\Application;
require 'vendor/autoload.php';

(new Dotenv\Dotenv(__DIR__ . "/../env"))->load();

$app = new Application();
$app->register(App\Providers\S3ServiceProvider::class);

$app->post('/upload', function (Request $request, Application $app, S3Client $s3) {
    $metadata = json_decode($request->get('metadata'), true);
    $fileName = $_FILES['file']['name'];
    $fileType = $_FILES['file']['type'];
    $tmpName  = $_FILES['file']['tmp_name'];

    try {
        $key = date('YmdHis') . "_" . $fileName;
        $s3->putObject([
            'Bucket'      => getenv('s3bucket'),
            'Key'         => $key,
            'SourceFile'  => $tmpName,
            'ContentType' => $fileType,
            'Metadata'    => $metadata,
        ]);
        unlink($tmpName);

        return response()->json([
            'status' => true,
            'key'    => $key,
        ]);
    } catch (Aws\S3\Exception\S3Exception $e) {
        return response()->json([
            'status' => false,
            'error'  => $e->getMessage(),
        ]);
    }
});

$app->run();