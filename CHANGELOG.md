Changelog
=========

Releases for CakePHP 5.0
------------------------
* 2.4.0
    * New: multipart upload support for large files — `/uppy/files/create-multipart-upload`, `sign-part`, `complete-multipart-upload`, `abort-multipart-upload`; parts are uploaded directly to S3 (or R2), bypassing PHP memory/timeout limits
    * New: `UppyHelper::getUploadConfig()` exposing `MaxFileSize`/`MultipartThreshold` to the browser via `window.UppyUploadConfig`
    * New: `Uppy.routes` config key (default `true`) — `/uppy/*` routes are registered regardless of `debug`, so the API works in production; set to `false` to disable
    * New: `S3Trait::_getS3Client()` backward-compatible alias (deprecated) delegating to `getS3Client()`
    * New: PHPUnit test harness runs standalone with `failOn*` and deprecation-as-error; tests cover multipart, routing, table/entity, and storage adapters
    * Change: minimum PHP raised to 8.2; `cakephp/cakephp` `^5.1`; declared previously-undeclared deps (`laminas/laminas-diactoros`, `psr/http-message`); `guzzlehttp/psr7` lower bound `^2.6.3`; PHPUnit `^11.5`
    * Change: `deleteObject()` uses `doesObjectExistV2()` instead of the deprecated `doesObjectExist()`
    * Fix: multipart uploads can be saved — `completeMultipartUpload()` now registers the object key as a server-issued path for `save()`, unblocking the documented multipart → save flow
    * Chore: phpcs (CakePHP standard) and PHPStan level 8 clean over `src/` and `tests/`, no baseline
    * Deprecated: `Uppy.Props.deleteFileS3` → use `deleteFileStorage`
    * **Breaking changes**: see UPGRADE.md for the full migration guide

* 2.3.0
    * New: `S3Trait::setPublicPermissions(string $key)` — sets `public-read` ACL on an S3 object (ported from 1.x)
    * New: `S3Trait::listFilesWithUrls(string $prefix, int $maxKeys)` — lists objects and appends a presigned GET URL to each (ported from 1.x)
    * New: `S3Trait::listFiles(string $prefix, int $maxKeys)` — lists objects in an S3 bucket filtered by prefix (ported from 1.x)
    * New: unit tests for all three methods covering happy path, empty bucket, prefix/maxKeys forwarding, and `AwsException` wrapping

* 2.2.0
    * New: pluggable storage drivers via `StorageAdapterInterface` and `AdapterFactory`
    * New: Google Cloud Storage (`gcs`) adapter (requires `google/cloud-storage`)
    * New: Cloudflare R2 (`r2`) adapter
    * New: config key `Uppy.driver` (env `UPPY_DRIVER`) selects the active backend
    * Rename: `Uppy.Props.deleteFileS3` → `deleteFileStorage`; old key still honored for backwards compatibility

* 2.1.0
    * Security: session upload token is now consumed immediately on first use — reuse after any validation failure is rejected
    * Security: unauthenticated requests to `view` and `delete` are blocked when file `user_id` is NULL (null-null comparison bypass fixed)
    * Security: `save` action verifies that the related record belongs to the current user before associating files
    * Security: `File` entity `_accessible` hardened — `hash`, `path`, `adapter`, `metadata`, `created`, `modified` can no longer be mass-assigned by the client
    * Fix: `sign` response now includes a `key` field; front-end JS updated to read `serverKey` from the sign response instead of parsing the S3 upload URL
    * Fix: `S3Trait` config key corrected (`contants` → `constants`); `Configure::readOrFail` used throughout
    * Fix: `folderExists` array-vs-int comparison corrected — empty Contents list now correctly throws instead of silently returning false
    * Fix: `filter_var` wrapper removed from S3 credential env reads (was masking null values)
    * New: migration `20260525000001_AddUserIdIndexToUppyFiles` adds an index on `uppy_files.user_id`
    * New: migration `20260525000002_ChangeFileSizeInUppyFilesTable` widens `filesize` from `INT` to `BIGINT` (supports files > 2 GB)
    * **Breaking changes**: see UPGRADE.md for full migration guide

* 2.0.3
    * Update documentation

* 2.0.2
    * Improvements after testing the plugin with an example application

* 2.0.1
    * Minor fixes

* 2.0.0
    * Initial release


Releases for CakePHP 4.0
------------------------
* 1.0.0
    * Initial release
