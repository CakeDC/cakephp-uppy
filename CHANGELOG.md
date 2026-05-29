Changelog
=========

Releases for CakePHP 5.0
------------------------
* 2.3.0
    * New: `S3Trait::setPublicPermissions(string $key)` — sets `public-read` ACL on an S3 object
    * New: `S3Trait::listFilesWithUrls(string $prefix, int $maxKeys)` — lists objects and appends a presigned GET URL to each
    * New: `S3Trait::listFiles(string $prefix, int $maxKeys)` — lists objects in an S3 bucket filtered by prefix

* 2.2.0
    * New: pluggable storage drivers via `StorageAdapterInterface` and `AdapterFactory`
    * New: Google Cloud Storage (`gcs`) adapter (requires `google/cloud-storage`)
    * New: Cloudflare R2 (`r2`) adapter
    * New: config key `Uppy.driver` (env `UPPY_DRIVER`) selects the active backend
    * Rename: `Uppy.Props.deleteFileS3` → `deleteFileStorage`; old key still honored for backwards compatibility

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
