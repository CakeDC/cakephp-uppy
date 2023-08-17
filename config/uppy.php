<?php
declare(strict_types=1);

return [
    'Uppy' => [
        'Props' => [
            'usersAliasModel' => 'Users',
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
            'contants' => [
                'lifeTimeGetObject' => '+20 minutes',
                'lifeTimePutObject' => '+5 minutes',
            ],
            'config' => [
                'version' => 'latest',
                'connection' => 'real', //dummy
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
