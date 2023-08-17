CakeDC Uppy Plugin
======================

You can install this plugin into your CakePHP application using [composer](https://getcomposer.org).

Requirements
------------

* CakePHP 5.0+
* PHP 8.1+


Setup
-----

The recommended way to install composer packages is:

`composer require cakedc/cakephp-uppy`

This plugin uses `uppy_files` as a table to store filedata as filename, filesize, path in S3, ...

To create table run in console `bin/cake migrations migrate`

You must configure the connection parameters with S3 in `config/uppy.php`

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


Support
-------

For bugs and feature requests, please use the [issues](https://github.com/cakedc/categories/issues) section of this repository.

Commercial support is also available, [contact us](https://www.cakedc.com/contact) for more information.

Contributing
------------

This repository follows the [CakeDC Plugin Standard](https://www.cakedc.com/plugin-standard). If you'd like to contribute new features, enhancements or bug fixes to the plugin, please read our [Contribution Guidelines](https://www.cakedc.com/contribution-guidelines) for detailed instructions.

License
-------

Copyright 2017-2023 Cake Development Corporation (CakeDC). All rights reserved.

Licensed under the [MIT](http://www.opensource.org/licenses/mit-license.php) License. Redistributions of the source code included in this repository must retain the copyright notice found in each file.


