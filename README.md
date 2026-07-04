CakeDC Uppy Plugin
======================

The **Uppy** plugin covers the following features:

* Upload files directly from the browser to a configured cloud storage backend using a presigned URL
* **Multipart upload support** for large files (>100MB) - files are uploaded in parts directly to S3, bypassing PHP memory/timeout limits
* Pluggable storage drivers: **Amazon S3**, **Google Cloud Storage**, and **Cloudflare R2** (selectable via `Uppy.driver`)
* Persist file path, mime type, date, metadata and the relation to the user (or other related model) in the `uppy_files` table

Requirements
------------

* CakePHP 5.0+
* PHP 8.1+

Configuration
-------------

You must configure the connection parameters for your storage backend in `config/uppy.php`:

```php
return [
    'Uppy' => [
        'driver' => 's3', // 's3' | 'gcs' | 'r2'
        'MaxFileSize' => null, // Maximum file size in bytes, null = no limit
        'MultipartThreshold' => 104857600, // 100 MiB - use multipart for files above this size
        'AcceptedContentTypes' => ['application/pdf', 'image/png', ...],
        'AcceptedExtensions' => ['pdf', 'png', ...],
        'S3' => [
            'constants' => [
                'lifeTimeGetObject' => '+20 minutes',
                'lifeTimePutObject' => '+5 minutes',
                'lifeTimeUploadPart' => '+20 minutes',
            ],
            'config' => [
                'version' => 'latest',
                'region' => env('S3_REGION'),
                'use_path_style_endpoint' => (bool)env('S3_USE_PATH_STYLE_ENDPOINT', false),
                'credentials' => [
                    'key' => env('S3_KEY'),
                    'secret' => env('S3_SECRET'),
                ],
            ],
            'bucket' => env('S3_BUCKET'),
        ],
    ],
];
```

**Configuration Options:**
- `MaxFileSize` - Optional maximum upload size in bytes; when set, `filesize` in sign/create requests is validated server-side (`null` = no limit)
- `MultipartThreshold` - File size in bytes above which the client should use multipart upload (default 100 MiB); exposed to the browser via `UppyHelper::getUploadConfig()` and `window.UppyUploadConfig`
- `AcceptedContentTypes` - List of MIME types allowed for storage in S3 and saved in database
- `AcceptedExtensions` - List of file extensions allowed for storage
- `lifeTimeGetObject` - TTL for presigned GET URLs
- `lifeTimePutObject` - TTL for presigned PUT URLs (simple uploads)
- `lifeTimeUploadPart` - TTL for presigned part upload URLs (multipart uploads)
- `use_path_style_endpoint` - Set `true` for MinIO and other S3-compatible stores (or via `S3_USE_PATH_STYLE_ENDPOINT` env)

Endpoints
---------

The plugin provides the following API endpoints:

**Simple Uploads:**
- `/uppy/files/sign` - Generate presigned PUT URL for direct browser-to-S3 upload (small files)
- `/uppy/files/save` - Save file metadata to database after upload
- `/uppy/files/delete` - Delete file from database and optionally from storage
- `/uppy/files/view/{id}` - Redirect to presigned GET URL for file access

**Multipart Uploads (for files >100MB):**
- `/uppy/files/create-multipart-upload` - Start a multipart upload; returns `uploadId` and object `key`
- `/uppy/files/sign-part` - Presign a single multipart upload part
- `/uppy/files/complete-multipart-upload` - Complete a multipart upload with part ETags
- `/uppy/files/abort-multipart-upload` - Abort an in-progress multipart upload

See [Multipart Upload Documentation](Docs/Documentation/Multipart-Upload.md) for detailed information about large file uploads.

Documentation
-------------

For documentation, as well as tutorials, see the [Docs](Docs/Home.md) directory of this repository.

**Additional Documentation:**
- [Multipart Upload Guide](Docs/Documentation/Multipart-Upload.md) - Detailed guide for handling large file uploads

Support
-------

For bugs and feature requests, please use the [issues](https://github.com/CakeDC/cakephp-inertia/issues) section of this repository.

Commercial support is also available, [contact us](https://www.cakedc.com/contact) for more information.

Contributing
------------

This repository follows the [CakeDC Plugin Standard](https://www.cakedc.com/plugin-standard). If you'd like to contribute new features, enhancements or bug fixes to the plugin, please read our [Contribution Guidelines](https://www.cakedc.com/contribution-guidelines) for detailed instructions.

License
-------

Copyright 2024 - 2026 Cake Development Corporation (CakeDC). All rights reserved.

Licensed under the [MIT](http://www.opensource.org/licenses/mit-license.php) License. Redistributions of the source code included in this repository must retain the copyright notice found in each file.


