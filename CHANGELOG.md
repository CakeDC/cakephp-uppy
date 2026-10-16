Changelog
=========

Releases for CakePHP 5.0
------------------------
* 2.4.0
    * **Breaking (security)** — ownership is now enforced on `view`/`delete`/`save`, `sign` and the multipart endpoints require an authenticated user, and `save()` accepts only server-issued keys. See UPGRADE.md for the full migration guide.
    * New: multipart upload support for large files — `/uppy/files/create-multipart-upload`, `sign-part`, `complete-multipart-upload`, `abort-multipart-upload`; parts are uploaded directly to S3 (or R2), bypassing PHP memory/timeout limits; `completeMultipartUpload()` registers the object key as a server-issued path, so the documented multipart → `save` flow works
    * New: `UppyHelper::getUploadConfig()` exposing `MaxFileSize`/`MultipartThreshold` to the browser via `window.UppyUploadConfig`
    * New: `Uppy.routes` config key (default `true`) — the known `/uppy/*` endpoints (`sign`, `save`, the multipart endpoints, `view`, `delete`, `index`) are registered regardless of `debug` so the API works in production, and only those (no catch-all fallbacks); the demo pages (`add`, `drag`) are registered only when `debug` is on; set to `false` to disable the endpoints
    * New: PHPUnit test harness runs standalone with `failOn*` and deprecation-as-error; tests cover multipart, routing, table/entity, and storage adapters
    * Change: minimum PHP raised to 8.2; `cakephp/cakephp` `^5.1`; declared previously-undeclared deps (`laminas/laminas-diactoros`, `psr/http-message`); `guzzlehttp/psr7` lower bound `^2.6.3`; PHPUnit `^11.5`
    * Change: `deleteObject()` uses `doesObjectExistV2()` instead of the deprecated `doesObjectExist()`
    * Security: `sign`, the multipart endpoints and `index` now require an authenticated user — unauthenticated requests to them return HTTP 401 — and `index` lists only the current user's files
    * Security: multipart endpoints reject an `uploadId` that was not created in the same session (HTTP 403), preventing cross-session completion/abort or key injection
    * Fix: `save()` accepts only server-issued keys (the session token restored from 2.1.0); tokens are consumed only after a successful save, so a failed batch can be retried without orphaning the uploaded objects
    * New: `Uppy.currentUserIdResolver` config key — resolve the current user id for ownership checks in applications with non-standard auth, without subclassing `FilesController`; an empty-string id is treated as unauthenticated
    * New: `Uppy.pendingUploadsTtl` config key (seconds, default `3600`; `0` disables) — expiry for the one-time upload tokens registered by `sign()` and the multipart endpoints
    * New: `Uppy.ownerIdResolver` config key — resolve the owner of the related record in `save()` for applications whose owner column is not `<usersModel>_id`
    * Chore: phpcs (CakePHP standard) and PHPStan level 8 clean over `src/` and `tests/`, no baseline
    * Deprecated: `Uppy.Props.deleteFileS3` → use `deleteFileStorage`
    * Note: the 2.2.x and 2.3.x lines were branched before the 2.1.0 security fixes and do not include them; upgrade to 2.4.0 to pick them up

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
