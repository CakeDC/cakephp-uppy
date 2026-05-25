Changelog
=========

Releases for CakePHP 5.0
------------------------
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
