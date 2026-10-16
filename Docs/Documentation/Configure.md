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

By default the plugin registers only its known endpoints under `/uppy` (`sign`, `save`, the multipart endpoints, `view`, `delete` and `index`) **regardless of `debug`**, so the API is reachable in production. The demo pages (`add`, `drag`) are registered only when `debug` is on. Set `Uppy.routes` to `false` in `config/uppy.php` to disable the endpoints entirely. `sign`, the multipart endpoints and `index` require an authenticated user, and `index` lists only the current user's files. You should still protect these endpoints with your application's authentication/authorization.

### Default uppy config file

```php
<?php

return [
    'Uppy' => [
        // Storage driver: 's3' | 'gcs' | 'r2'
        'driver' => env('UPPY_DRIVER', 's3'),

        // Register the /uppy/* routes (default true)
        'routes' => true,

        // Resolve the current user id (default null = identity → Auth.userId)
        'currentUserIdResolver' => null,

        // Lifetime in seconds of the session upload tokens (default 3600; 0 disables)
        'pendingUploadsTtl' => 3600,

        // Resolve the owner of the related record in save() (default null = <usersModel>_id)
        'ownerIdResolver' => null,

        'Props' => [
            'usersAliasModel' => 'Users',
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
                'connection' => env('S3_CONNECTION', 'real'),
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

- `usersAliasModel` — association alias for the `belongsTo` users relation on `FilesTable`, and the table used by the `existsIn('user_id', …)` rule; default `Users`
- `usersModel` — users table/alias used to resolve the owner of a file when saving; default `Users`
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

## Authentication

`sign`, the multipart endpoints and `index` require an authenticated user, and `view`, `delete` and `save` enforce ownership by comparing the record's `user_id` with the current user id. The current user id is resolved, in order, from:

1. `Uppy.currentUserIdResolver` — when set, a callable that receives the `ServerRequest` and returns the user id (`string`/`int`) or `null`. It is authoritative: a `null` result means "no user".
2. The `identity` request attribute set by `cakephp/authentication` — the supported source.
3. The `Auth.userId` session key — **not** written by `cakephp/authentication`, `AuthComponent` or CakeDC/Users; it exists only for applications (and the plugin's tests) that set it explicitly.

Requests without an authenticated user get HTTP **401** on `sign`, the multipart endpoints, `index`, `view` and `delete` (the JSON endpoints return the plugin's `{error, code, message}` envelope); `save()` returns HTTP **200** with `{ "error": true, ... }` instead. An authenticated user who does not own the record gets HTTP **403**.

Applications using a non-standard auth mechanism should set the resolver instead of subclassing `FilesController` — the plugin routes (`/uppy/files/*`) and the bundled `assets.php` always target `CakeDC/Uppy.Files`, so a subclass is never invoked for them.

`cakephp/authentication` (the `identity` attribute) is the default and needs no configuration. To use a non-standard source, set `currentUserIdResolver` to one of the following — each is a separate `config/uppy.php`, since an array cannot hold the same key twice:

```php
// Legacy AuthComponent (session stores Auth.User as an array):
'Uppy' => [
    'currentUserIdResolver' => fn(\Cake\Http\ServerRequest $request) =>
        $request->getSession()->read('Auth.User.id'),
],
```

```php
// A custom session key:
'Uppy' => [
    'currentUserIdResolver' => fn(\Cake\Http\ServerRequest $request) =>
        $request->getSession()->read('MyCustomAuth.userId'),
],
```

### Ownership of the related record (`save`)

`save()` also checks that the record a file is attached to belongs to the current user. By default the owner id is read from the `<Uppy.Props.usersModel>_id` column (e.g. `user_id`) on that record. **Attaching files to a `Users` record therefore needs `Uppy.ownerIdResolver`**: the related record *is* the user, and a users table has no `user_id` column of its own. Because the resolver applies to every table, branch on the record's source so other records keep using their `user_id` column:

```php
'Uppy' => [
    'ownerIdResolver' => fn($record) => $record->getSource() === 'Users'
        ? $record->get('id')
        : $record->get('user_id'),
],
```

Set `Uppy.ownerIdResolver` for any table whose owner column is not `<usersModel>_id`, or to express a computed or shared owner. The resolver replaces the default for all tables; `getSource()` returns the alias sent as model (e.g. `CakeDC/Users.Users`). The resolver receives the record and the `ServerRequest`; see UPGRADE.md §17 for details.

For privileged workflows (e.g., admins accessing files they do not own) the single-user-id resolver is not enough: set `Uppy.routes` to `false` and route `/uppy/files/*` to your own controller (and override the URLs built in `templates/element/assets.php`).

## Endpoints

All endpoints are registered by default (see `Uppy.routes`). `sign`, the multipart endpoints and `index` require an authenticated user; `view`, `delete` and `save` enforce ownership. Protect them with authentication in production as well.

**Simple uploads:**

- `/uppy/files/sign` — returns a signed URL for uploading a file directly from the browser to the configured storage
- `/uppy/files/save` — persists the database record for a file just uploaded, with the correct storage path
- `/uppy/files/delete` — deletes the database record and, when `Uppy.Props.deleteFileStorage` is `true`, also removes the object from storage
- `/uppy/files/view` — returns a signed URL for reading the file from the configured storage

**Multipart uploads** (for large files; S3 and R2 drivers):

- `/uppy/files/create-multipart-upload` — starts a multipart upload and returns `uploadId` and object `key`
- `/uppy/files/sign-part` — presigns a single part upload
- `/uppy/files/complete-multipart-upload` — completes the upload with the part ETags; the resulting key is accepted by `/uppy/files/save`
- `/uppy/files/abort-multipart-upload` — aborts an in-progress multipart upload

See [Multipart Upload](Multipart-Upload.md) for the full flow.

## Sample pages

The index page `/uppy/files` is **always routed** (see above); it lists the current user's files with view/delete links.

The following example pages are registered **only when `debug` is on**, and are intended for development/demo:

- `/uppy/files/add` — example single-file upload to the configured storage
- `/uppy/files/drag` — example multi-file drag-and-drop upload using Uppy Dashboard
