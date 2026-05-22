Changelog
=========

Releases for CakePHP 5.0
------------------------
* 2.1.0
    * Add pluggable storage drivers via `StorageAdapterInterface` and `AdapterFactory`
    * Add Google Cloud Storage (`gcs`) adapter (requires `google/cloud-storage`)
    * Add Cloudflare R2 (`r2`) adapter
    * New config key `Uppy.driver` (env `UPPY_DRIVER`) selects the active backend
    * Rename `Uppy.Props.deleteFileS3` to `deleteFileStorage`; the old key is still honored for backwards compatibility

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
