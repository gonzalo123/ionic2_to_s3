Taking photos with a ionic2 and upload to S3 Bucket with SAP's Cloud Foundry, Silex and Lumen
======

Today I want to play with an experiment. When I work with mobile applications, I normally use ionic and on-premise backends. Today I want play with cloud based backends. In this small experiment I want to use an ionic2 application to take pictures and upload them to an S3 bucket. Let's start.

First I've created a simple ionic2 application. It's a very simple application. Only one page with a button to trigger the device's camera.

```html
<ion-header>
    <ion-navbar>
        <ion-title>
            Photo
        </ion-title>
    </ion-navbar>
</ion-header>

<ion-content padding>
    <ion-fab bottom right>
        <button ion-fab (click)="takePicture()">
            <ion-icon  name="camera"></ion-icon>
        </button>
    </ion-fab>
</ion-content>
```

The controller uses @ionic-native/camera to take photos and later we use @ionic-native/transfer to upload them to the backend.

```js
import {Component} from '@angular/core';
import {Camera, CameraOptions} from '@ionic-native/camera';
import {Transfer, FileUploadOptions, TransferObject} from '@ionic-native/transfer';
import {ToastController} from 'ionic-angular';
import {LoadingController} from 'ionic-angular';

@Component({
    selector: 'page-home',
    templateUrl: 'home.html'
})
export class HomePage {
    constructor(private transfer: Transfer,
                private camera: Camera,
                public toastCtrl: ToastController,
                public loading: LoadingController) {
    }

    takePicture() {
        const options: CameraOptions = {
            quality: 100,
            destinationType: this.camera.DestinationType.FILE_URI,
            sourceType: this.camera.PictureSourceType.CAMERA,
            encodingType: this.camera.EncodingType.JPEG,
            targetWidth: 1000,
            targetHeight: 1000,
            saveToPhotoAlbum: false,
            correctOrientation: true
        };

        this.camera.getPicture(options).then((uri) => {
            const fileTransfer: TransferObject = this.transfer.create();

            let options: FileUploadOptions = {
                fileKey: 'file',
                fileName: uri.substr(uri.lastIndexOf('/') + 1),
                chunkedMode: true,
                headers: {
                    Connection: "close"
                },
                params: {
                    metadata: {foo: 'bar'},
                    token: 'mySuperSecretToken'
                }
            };

            let loader = this.loading.create({
                content: 'Uploading ...',
            });

            loader.present().then(() => {
                let s3UploadUri = 'https://myApp.cfapps.eu10.hana.ondemand.com/upload';
                fileTransfer.upload(uri, s3UploadUri, options).then((data) => {
                    let message;
                    let response = JSON.parse(data.response);
                    if (response['status']) {
                        message = 'Picture uploaded to S3: ' + response['key']
                    } else {
                        message = 'Error Uploading to S3: ' + response['error']
                    }
                    loader.dismiss();
                    let toast = this.toastCtrl.create({
                        message: message,
                        duration: 3000
                    });
                    toast.present();
                }, (err) => {
                    loader.dismiss();
                    let toast = this.toastCtrl.create({
                        message: "Error",
                        duration: 3000
                    });
                    toast.present();
                });
            });
        });
    }
}
```

Now let's work with the backend. We'll use SAP's Cloud Foundry tenant (using a free account). In this tenant we'll create a PHP application using the PHP buildpack with nginx


```yaml
applications:
- name:    myApp
  path: .
  memory:  128MB
  buildpack: php_buildpack
```

The PHP application is a simple Silex application to handle the file uploads and post the pictures to S3 using the official AWS SDK for PHP (based on Guzzle)

```php
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

    if ($token === $_ENV['token']) {
        $fileName = $_FILES['file']['name'];
        $fileType = $_FILES['file']['type'];
        $tmpName  = $_FILES['file']['tmp_name'];

        /** @var \Aws\S3\S3Client $s3 */
        $s3 = $app['aws'];
        try {
            $key = date('YmdHis') . "_" . $fileName;
            $s3->putObject([
                'Bucket'      => $_ENV['s3bucket'],
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
```

I just wanted a simple prototype (a working one). Enough for a Sunday morning hacking.

UPDATE

I had this post ready weeks ago but something has changed. Silex is dead so, as an exercise I'll migrate current Silex application to Lumen.

That's the main application.

```php
use App\Http\Middleware;
use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Laravel\Lumen\Application;

require 'vendor/autoload.php';

(new Dotenv\Dotenv(__DIR__ . "/../env"))->load();

$app = new Application();

$app->routeMiddleware([
    'auth' => Middleware\AuthMiddleware::class,
]);

$app->register(App\Providers\S3ServiceProvider::class);

$app->group(['middleware' => 'auth'], function (Application $app) {
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
});

$app->run();
```

Probably we can find a S3 Service provider, but I've built a simple one for this example.

```php
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

            error_log(json_encode($conf));
            return new S3Client($conf);
        });
    }
}
```

And also I'm using a middleware for the authentication

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->get('token');
        if ($token === getenv('token')) {
            return response('Admin Login', 401);
        }

        return $next($request);
    }
}
```

Ok. I'll post this article soon. At least before Lumen will be dead also, and I need to rewrite it too :)


