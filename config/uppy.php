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
 *
 * Uppy plugin configuration template.
 * Copy this file to your application's config/ directory and customise it.
 *
 * Required env vars per driver:
 *   s3:  S3_REGION, S3_KEY, S3_SECRET, S3_BUCKET
 *   gcs: GCS_PROJECT_ID, GCS_KEY_FILE_PATH, GCS_BUCKET
 *   r2:  R2_ACCOUNT_ID, R2_KEY, R2_SECRET, R2_BUCKET, R2_PUBLIC_DOMAIN
 */
return [
    'Uppy' => [
        /*
         * Storage driver: 's3' | 'gcs' | 'r2'
         * Set UPPY_DRIVER in your .env file or override here.
         */
        'driver' => env('UPPY_DRIVER', 's3'),

        'Props' => [
            'usersModel' => 'Users',
            /*
             * When true and a file record is deleted, the object is also
             * removed from storage. Renamed from deleteFileS3 to apply to
             * all drivers. Falls back to deleteFileS3 for backwards compat.
             */
            'deleteFileStorage' => true,
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
         * Amazon S3 — used when driver = 's3'
         * Set connection = 'dummy' in test environments.
         */
        'S3' => [
            'constants' => [
                'lifeTimeGetObject' => '+20 minutes',
                'lifeTimePutObject' => '+5 minutes',
            ],
            'config' => [
                'version' => 'latest',
                'region' => env('S3_REGION', null),
                'credentials' => [
                    'key' => env('S3_KEY', null),
                    'secret' => env('S3_SECRET', null),
                ],
            ],
            'bucket' => env('S3_BUCKET', null),
        ],

        /*
         * Google Cloud Storage — used when driver = 'gcs'
         * Requires: composer require google/cloud-storage ^1.50
         */
        'GCS' => [
            'projectId' => env('GCS_PROJECT_ID', null),
            'keyFilePath' => env('GCS_KEY_FILE_PATH', null),
            'bucket' => env('GCS_BUCKET', null),
            'lifeTimePutObject' => '+5 minutes',
            'lifeTimeGetObject' => '+20 minutes',
        ],

        /*
         * Cloudflare R2 — used when driver = 'r2'
         * R2 is S3-compatible; uses AWS SDK with a custom endpoint.
         * publicDomain: your custom domain or pub-<hash>.r2.dev URL.
         * Set connection = 'dummy' in test environments.
         */
        'R2' => [
            'bucket' => env('R2_BUCKET', null),
            'publicDomain' => env('R2_PUBLIC_DOMAIN', null),
            'constants' => [
                'lifeTimePutObject' => '+5 minutes',
                'lifeTimeGetObject' => '+20 minutes',
            ],
            'config' => [
                'version' => env('R2_VERSION', 'latest'),
                'region' => env('R2_REGION', 'auto'),
                'endpoint' => env('R2_ENDPOINT', null),
                'credentials' => [
                    'key' => env('R2_KEY', null),
                    'secret' => env('R2_SECRET', null),
                ],
                'use_path_style_endpoint' => true,
            ],
        ],
    ],
];
