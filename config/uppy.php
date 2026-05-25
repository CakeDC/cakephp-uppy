<?php
declare(strict_types=1);

/**
 * Copyright 2023, Cake Development Corporation (https://www.cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2023, Cake Development Corporation (https://www.cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
return [
    'Uppy' => [
        'Props' => [
            // the Table Alias to identify the user who uploaded the file
            'usersAliasModel' => 'Users',
            // the Table className to identify the user who uploaded the file
            'usersModel' => 'Users',
            'deleteFileS3' => true,
            'tableFiles' => 'uppy_files',
        ],
        'AcceptedContentTypes' => [
            'application/pdf',
            'image/png',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ],
        'AcceptedExtensions' => [
            'pdf',
            'png',
            'doc',
            'docx',
            'xls',
            'ppt',
            'pptx',
        ],
        /*
        * S3 configuration to manage files
        */
        'S3' => [
            'constants' => [
                'lifeTimeGetObject' => '+20 minutes',
                'lifeTimePutObject' => '+5 minutes',
            ],
            'config' => [
                'version' => 'latest',
                'connection' => 'real', //dummy
                'region' => env('S3_REGION', null),
                'credentials' => [
                    'key'    => env('S3_KEY', null),
                    'secret' => env('S3_SECRET', null),
                ],
            ],
            'bucket' => env('S3_BUCKET', null),
        ],
    ],
];
