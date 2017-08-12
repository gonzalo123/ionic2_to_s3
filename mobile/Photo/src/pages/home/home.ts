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
        console.log("takePicture ");
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
                    token: 'super_secret_token'
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
