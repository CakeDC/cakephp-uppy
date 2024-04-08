## Configure

To configure the plugin first copy the base configuration to your project

```
$> cp vendor/cakedc/cakephp-uppy/config/uppy.php config/uppy.php
```

Then run migration to create table *uppy_files* where save data for files uploaded

```
$> bin/cake migrations migrate -p CakeDC/Uppy
```

Note: you can change the name of this table in the configuration file *config/uppy.php*

## Configuration file *config/uppy.php*

By default, when debug is set to true the plugin will add a number of routes to your application `/uppy/files` in order to
upload and view the uploaded files. You should protect these endpoints in case your application requires
authentication to upload or manage files. Failing to do so would allow an authenticated user to *upload* files
to your application.

### Default uppy config file:

```php
<?php

return [
    'Uppy' => [
        'Props' => [
            'usersModel' => 'Users',
            'deleteFileS3' => true,
            'tableFiles' => 'uppy_files',
        ],
        'AcceptedContentTypes' => [
            'application/pdf',
            'image/png',
        ],
        'AcceptedExtensions' => [
            'pdf',
            'png',
        ],
        /*
        * S3 configuration to manage files
        */
        'S3' => [
            'contants' => [
                'lifeTimeGetObject' => '+20 minutes',
                'lifeTimePutObject' => '+5 minutes',
            ],
            'config' => [
                'version' => 'latest',
                'region' => filter_var(env('S3_REGION', null)),
                'endpoint' => filter_var(env('S3_END_POINT', null)),
                'credentials' => [
                    'key' => filter_var(env('S3_KEY', null)),
                    'secret' => filter_var(env('S3_SECRET', null)),
                ],
            ],
            'bucket' => filter_var(env('S3_BUCKET')),
        ],
    ],
];
```

- usersModel = is the alias name used in you app
- deleteFileS3 = if the record of the file in the database is deleted and it's marked true, the deletion is launched in the S3 deposit
- tableFiles = name of table used to store file data, default is `uppy_files`
- AcceptedContentTypes = list of content-type stored in S3 and saved in database
- AcceptedExtensions = list of file extensions stored in S3 and saved in database
- lifeTimeGetObject = life time generated link to access file in S3
- lifeTimePutObject = life time generated link to post file in S3
- region = configured region S3
- endpoint = endpoint server to PUT/POST/GET S3 files
- key = S3 account key
- secret = S3 account secret
- bucket = bucket name

Enpoints
-------

- /uppy/files/sign = sign with credentials and return signed url to upload file in S3 directly in front
- /uppy/files/save = save register just uploaded in database with correct S3 path
- /uppy/files/delete = delete register in database, if Uppy.Props.deleteFileS3 is true remove from S3
- /uppy/files/view = sign with credentials and return signed url to access file in S3 directly in front

Sample
-------

- /uppy/files = list of files in database and link to view/delete in S3
- /uppy/files/add = example uppy upload file to configured S3 and save data relationed in database
- /uppy/files/drag = example uppy drag and upload multiple file, using Dashboard to configured S3 and save data relationed in database
