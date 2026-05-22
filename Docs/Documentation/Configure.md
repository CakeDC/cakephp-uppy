## Configure

To configure the plugin first copy the base configuration to your project

```
$> cp vendor/cakedc/cakephp-uppy/config/uppy.php config/uppy.php
```

Then run migration to create table *uppy_files* where data for uploaded files is stored

```
$> bin/cake migrations migrate -p CakeDC/Uppy
```

Note: you can change the name of this table in the configuration file *config/uppy.php*

## Storage drivers

The plugin supports three storage backends, selected via `Uppy.driver`:

| Driver  | Backend                  | Required env vars                                              | Extra packages                       |
|---------|--------------------------|----------------------------------------------------------------|--------------------------------------|
| `s3`    | Amazon S3                | `S3_REGION`, `S3_KEY`, `S3_SECRET`, `S3_BUCKET`                | none (uses `aws/aws-sdk-php`)        |
| `gcs`   | Google Cloud Storage     | `GCS_PROJECT_ID`, `GCS_KEY_FILE_PATH`, `GCS_BUCKET`            | `composer require google/cloud-storage:^1.50` |
| `r2`    | Cloudflare R2            | `R2_ACCOUNT_ID`, `R2_KEY`, `R2_SECRET`, `R2_BUCKET`, `R2_PUBLIC_DOMAIN`, `R2_ENDPOINT` | none (R2 is S3-compatible)  |

Set the driver via the `UPPY_DRIVER` env var (`s3` | `gcs` | `r2`), or override `Uppy.driver` directly in `config/uppy.php`. Default is `s3`.

All drivers implement `CakeDC\Uppy\Storage\StorageAdapterInterface` and are instantiated by `CakeDC\Uppy\Storage\AdapterFactory::create()`. To add a custom driver, implement the interface and register a new case in the factory.

## Configuration file *config/uppy.php*

By default, when debug is set to true the plugin will add a number of routes to your application under `/uppy/files` for uploading and viewing files. You should protect these endpoints if your application requires authentication to upload or manage files. Failing to do so would allow any visitor to *upload* files to your application.

### Default uppy config file

```php
<?php

return [
    'Uppy' => [
        // Storage driver: 's3' | 'gcs' | 'r2'
        'driver' => env('UPPY_DRIVER', 's3'),

        'Props' => [
            'usersModel' => 'Users',
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
            'pdf', 'png', 'doc', 'docx', 'xls', 'ppt', 'pptx',
        ],

        // Amazon S3 — used when driver = 's3'
        'S3' => [
            'constants' => [
                'lifeTimeGetObject' => '+20 minutes',
                'lifeTimePutObject' => '+5 minutes',
            ],
            'config' => [
                'version' => 'latest',
                'region' => env('S3_REGION', null),
                'credentials' => [
                    'key'    => env('S3_KEY', null),
                    'secret' => env('S3_SECRET', null),
                ],
            ],
            'bucket' => env('S3_BUCKET', null),
        ],

        // Google Cloud Storage — used when driver = 'gcs'
        // Requires: composer require google/cloud-storage ^1.50
        'GCS' => [
            'projectId'         => env('GCS_PROJECT_ID', null),
            'keyFilePath'       => env('GCS_KEY_FILE_PATH', null),
            'bucket'            => env('GCS_BUCKET', null),
            'lifeTimePutObject' => '+5 minutes',
            'lifeTimeGetObject' => '+20 minutes',
        ],

        // Cloudflare R2 — used when driver = 'r2'
        // R2 is S3-compatible; uses the AWS SDK with a custom endpoint.
        'R2' => [
            'bucket'       => env('R2_BUCKET', null),
            'publicDomain' => env('R2_PUBLIC_DOMAIN', null),
            'constants' => [
                'lifeTimePutObject' => '+5 minutes',
                'lifeTimeGetObject' => '+20 minutes',
            ],
            'config' => [
                'version'  => env('R2_VERSION', 'latest'),
                'region'   => env('R2_REGION', 'auto'),
                'endpoint' => env('R2_ENDPOINT', null),
                'credentials' => [
                    'key'    => env('R2_KEY', null),
                    'secret' => env('R2_SECRET', null),
                ],
                'use_path_style_endpoint' => true,
            ],
        ],
    ],
];
```

### Props

- `usersModel` — alias name used in your app for the users association
- `deleteFileStorage` — when `true`, deleting a file record also removes the object from the active storage backend. (Renamed from `deleteFileS3`; the old key still works as a fallback for backwards compatibility.)
- `tableFiles` — name of the table used to store file metadata, default `uppy_files`

### Accepted types

- `AcceptedContentTypes` — MIME types accepted for upload and persisted in the database
- `AcceptedExtensions` — file extensions accepted for upload and persisted in the database

### Per-driver options

- **S3 / R2** — `constants.lifeTimePutObject` / `constants.lifeTimeGetObject` control the signed URL lifetime for upload and read URLs respectively. `config` is passed directly to `Aws\S3\S3Client`.
- **GCS** — `lifeTimePutObject` / `lifeTimeGetObject` at the top level of the `GCS` block; `keyFilePath` is the path to a service-account JSON key file.
- **R2** — `publicDomain` is your custom domain or `pub-<hash>.r2.dev` URL; `endpoint` is the R2 S3-compatible endpoint (`https://<account-id>.r2.cloudflarestorage.com`).

In test environments, set `Uppy.S3.config.connection` or `Uppy.R2.config.connection` to `'dummy'` to bypass real network calls.

## Endpoints

- `/uppy/files/sign` — returns a signed URL for uploading a file directly from the browser to the configured storage
- `/uppy/files/save` — persists the database record for a file just uploaded, with the correct storage path
- `/uppy/files/delete` — deletes the database record and, when `Uppy.Props.deleteFileStorage` is `true`, also removes the object from storage
- `/uppy/files/view` — returns a signed URL for reading the file from the configured storage

## Sample pages (debug only)

These routes are only registered when `debug` is `true`:

- `/uppy/files` — list of files in the database with view/delete links
- `/uppy/files/add` — example single-file upload to the configured storage
- `/uppy/files/drag` — example multi-file drag-and-drop upload using Uppy Dashboard
