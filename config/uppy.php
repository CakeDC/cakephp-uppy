<?php
declare(strict_types=1);

/**
 * Copyright 2023 - 2026, Cake Development Corporation (https://www.cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2023 - 2026, Cake Development Corporation (https://www.cakedc.com)
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

        /*
         * Maximum file size in bytes. null = no limit.
         * When set, filesize in sign/create requests is validated server-side.
         */
        'MaxFileSize' => null,

        /*
         * File size threshold (in bytes) for using multipart upload.
         * Default: 104857600 (100 MiB)
         * Files above this size should use multipart upload for better reliability.
         */
        'MultipartThreshold' => 104857600,

        /*
         * Register the plugin routes under /uppy (sign, save, multipart, …).
         * Defaults to true so the API works in production; set to false to
         * disable the endpoints entirely. Protect them with authentication.
         */
        'routes' => true,

        /*
         * Resolves the current user id used by the ownership checks.
         * When null, the plugin reads the `identity` request attribute set by
         * cakephp/authentication and falls back to the `Auth.userId` session
         * key. Set a callable to support non-standard auth without subclassing
         * FilesController (the plugin routes always target CakeDC/Uppy.Files,
         * so a subclass is never invoked for them):
         *
         *   'currentUserIdResolver' => fn(\Cake\Http\ServerRequest $request) =>
         *       $request->getSession()->read('MyCustomAuth.userId'),
         *
         * The callable must return a string, an integer or null. When set, it
         * is authoritative: null (or an empty string) means "no user" and the
         * request is treated as unauthenticated.
         */
        'currentUserIdResolver' => null,

        'Props' => [
            'usersAliasModel' => 'Users',
            'usersModel' => 'Users',
            /*
             * When true and a file record is deleted, the object is also
             * removed from storage. Renamed from deleteFileS3 to apply to
             * all drivers; deleteFileS3 is @deprecated but still honored as
             * a fallback for backwards compatibility.
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
                'lifeTimeUploadPart' => '+20 minutes',
            ],
            'config' => [
                'version' => 'latest',
                'connection' => env('S3_CONNECTION', 'real'),
                'region' => env('S3_REGION', null),
                'use_path_style_endpoint' => (bool)env('S3_USE_PATH_STYLE_ENDPOINT', false),
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
                'lifeTimeUploadPart' => '+20 minutes',
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
