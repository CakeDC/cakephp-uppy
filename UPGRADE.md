# Upgrade Guide

## From any previous version to 2.x (security patch)

This release contains **breaking changes** across PHP configuration, the controller layer, the `File` entity, and the JavaScript bundles. All changes are security-motivated. Read each section carefully before upgrading.

---

### 1. Run the new migration

A new index on `uppy_files.user_id` improves query performance for the new ownership checks.

```bash
bin/cake migrations migrate -p CakeDC/Uppy
```

---

### 2. Rename the config key `contants` → `constants`

**Type:** Breaking — PHP application config

The typo `contants` has been corrected to `constants` in `config/uppy.php` and in `S3Trait`. If your application copied and customised `config/uppy.php` (or overrides any `Uppy.S3.contants.*` keys via `Configure::write()`), you must rename the key.

**Before:**
```php
'S3' => [
    'contants' => [
        'lifeTimeGetObject' => '+20 minutes',
        'lifeTimePutObject' => '+5 minutes',
    ],
    // ...
],
```

**After:**
```php
'S3' => [
    'constants' => [
        'lifeTimeGetObject' => '+20 minutes',
        'lifeTimePutObject' => '+5 minutes',
    ],
    // ...
],
```

Any call to `Configure::read('Uppy.S3.contants.*')` or `Configure::write('Uppy.S3.contants.*', ...)` must also be updated to use `constants`.

> **Why no Rector rule?** This is a string literal rename inside a `Configure::write()` array key. Standard Rector rules operate on PHP class/method/constant names, not on arbitrary string keys within array literals. A custom Rector rule would require a fully custom `AbstractRector` implementation for a single two-character change across typically one config file. The risk/effort ratio does not justify it. Use your editor's find-and-replace: search for `Uppy.S3.contants` and replace with `Uppy.S3.constants`.

---

### 3. Remove the `endpoint` key from `Uppy.S3.config`

**Type:** Breaking — PHP application config

The `endpoint` key was never consumed by the AWS S3 adapter within this plugin and has been removed from the default configuration. If your application relied on this key being present in the merged config, remove it. To configure a custom S3-compatible endpoint, use the AWS SDK's standard `endpoint` option directly in your own `S3Client` instantiation or via a service-level configuration outside this plugin.

**Before (`config/uppy.php` copy in your app):**
```php
'config' => [
    'version'    => 'latest',
    'connection' => 'real',
    'region'     => env('S3_REGION', null),
    'endpoint'   => env('S3_END_POINT', null),  // remove this line
    'credentials' => [
        'key'    => env('S3_KEY', null),
        'secret' => env('S3_SECRET', null),
    ],
],
```

**After:**
```php
'config' => [
    'version'    => 'latest',
    'connection' => 'real',
    'region'     => env('S3_REGION', null),
    'credentials' => [
        'key'    => env('S3_KEY', null),
        'secret' => env('S3_SECRET', null),
    ],
],
```

---

### 4. Remove `filter_var()` wrappers from config env reads

**Type:** Non-breaking (behavior-neutral) — PHP application config

`filter_var($value, FILTER_DEFAULT)` is a no-op: it returns its input unchanged. These wrappers have been removed from the default `config/uppy.php`. If your local copy still uses them (e.g., `filter_var(env('S3_REGION', null))`), you can safely remove the `filter_var()` call.

**Before:**
```php
'region' => filter_var(env('S3_REGION', null)),
'bucket' => filter_var(env('S3_BUCKET')),
```

**After:**
```php
'region' => env('S3_REGION', null),
'bucket' => env('S3_BUCKET', null),
```

---

### 5. `sign()` response now includes a `key` field — use it in `save()`

**Type:** Breaking — JavaScript clients / API consumers

`POST /uppy/files/sign` now returns an additional `key` field in the JSON response. This is the server-assigned S3 object key for the file being uploaded.

**New response shape:**
```json
{
    "error": false,
    "code": 200,
    "key": "550e8400-e29b-41d4-a716-446655440000-my-document.pdf",
    "method": "PUT",
    "url": "https://s3.amazonaws.com/...",
    "fields": [],
    "headers": { "content-type": "application/pdf" }
}
```

You **must** store `data.key` after calling `sign()` and send it as the `path` field when calling `save()`. See section 6 for why this is now enforced.

The bundled `webroot/js/add.js` and `webroot/js/drag.js` have been updated to call `uppy.setFileMeta(file.id, { serverKey: data.key })` immediately after receiving the `sign` response, then read `file.meta.serverKey` (or `result.successful[j].meta.serverKey` in `drag.js`) when building the `save()` payload.

If you publish your own JavaScript instead of using the bundled files, update it accordingly.

---

### 6. `save()` now validates the path against a server-issued session token

**Type:** Breaking — all clients

`POST /uppy/files/save` now checks that each item's `path` value was previously issued by the server during a `sign()` call in the same session. Paths that were not issued by the server (e.g., paths constructed or modified on the client) are rejected immediately with:

```json
{ "error": true, "message": "Invalid or unrecognized file path" }
```

HTTP status remains 200 (JSON API contract), but `error` is `true`.

Each token is **one-time use**: once a path has been accepted by `save()`, it cannot be reused.

**Migration:** Update the JavaScript that sends the `save()` request so that `obj.path` is set to the `key` value returned by `sign()`, not a value parsed from the S3 upload URL.

**Before (old pattern — no longer accepted):**
```js
// Parsing the key out of the upload URL — rejected by the new validation
let v = response.uploadURL.split('/')
obj.path = v[v.length - 1];
```

**After (required pattern):**
```js
// Use the server-assigned key stored during the sign() call
obj.path = file.meta.serverKey;
```

---

### 7. `view()` and `delete()` now enforce ownership

**Type:** Breaking — applications without ownership enforcement

`GET /uppy/files/view/:id` and `POST /uppy/files/delete/:id` now throw `ForbiddenException` (HTTP 403) if the `user_id` on the requested `File` record does not match the currently authenticated user.

Previously, any authenticated user could view or delete any file by guessing its ID. This is now blocked.

**Impact on admin or multi-user workflows:** If your application needs to allow privileged users (e.g., admins) to access files they do not own, override `getCurrentUserId()` in a subclass of `FilesController` in your application:

```php
// src/Controller/FilesController.php (in your application, not the plugin)
class FilesController extends \CakeDC\Uppy\Controller\FilesController
{
    protected function getCurrentUserId(): string|int|null
    {
        // Allow admins to access all files
        $identity = $this->getRequest()->getAttribute('identity');
        if ($identity && $identity->getOriginalData()['role'] === 'admin') {
            return $identity->getIdentifier(); // will match any file for admin
        }
        return parent::getCurrentUserId();
    }
}
```

For true admin bypass, you would return the file's `user_id` directly — but that requires loading the file before calling the check. A cleaner approach is to skip the check entirely for admins via a role guard before reaching the action.

---

### 8. `save()` now enforces ownership of the associated record

**Type:** Breaking — applications without per-user record scoping

When saving a file, `save()` now looks up `Uppy.Props.usersModel` to determine the ownership column on the related record (e.g., `user_id` for a `Users` model). If the related record does not belong to the currently authenticated user, the request is rejected with:

```json
{ "error": true, "message": "You are not authorized to associate files with this record" }
```

**Impact:** If you have records that legitimately belong to one user but files are being attached by a different user (e.g., a shared-record workflow), you must implement that logic in a subclass of `FilesController` by overriding the relevant logic or by overriding `getCurrentUserId()` to return the record owner's ID in those cases.

---

### 9. `getCurrentUserId()` — new protected method, override if needed

**Type:** Potentially breaking — applications using non-standard auth

A new `protected` method `getCurrentUserId()` has been added to `FilesController`. It reads the current user's ID from:

1. The PSR-7 `identity` request attribute set by `cakephp/authentication` middleware (preferred).
2. The `Auth.userId` session key (fallback for legacy CakeDC/Users setups and tests).

If your application uses a different authentication mechanism (e.g., API tokens, custom session keys), override this method:

```php
protected function getCurrentUserId(): string|int|null
{
    return $this->getRequest()->getSession()->read('MyCustomAuth.userId');
}
```

---

### 10. `File` entity: `_accessible` fields tightened

**Type:** Breaking — code that mass-assigns protected fields

The following fields are now `false` in `File::$_accessible`:

| Field       | Was   | Now   |
|-------------|-------|-------|
| `hash`      | `true` | `false` |
| `path`      | `true` | `false` |
| `adapter`   | `true` | `false` |
| `created`   | `true` | `false` |
| `modified`  | `true` | `false` |
| `metadata`  | `true` | `false` |

Code that relied on `newEntity(['path' => '...'])` or `patchEntity($file, ['adapter' => '...'])` will silently ignore those fields. The controller now sets `$file->path` explicitly after validating the session token.

**Migration:** Replace any mass-assignment of these fields with explicit property assignment:

```php
// Before (no longer works via newEntity/patchEntity)
$file = $this->Files->newEntity(['path' => $somePath, 'adapter' => 's3']);

// After
$file = $this->Files->newEntity([...other accessible fields...]);
$file->path = $somePath;
$file->adapter = 's3';
```

---

### 11. `delete` action added to `FormProtection` unlocked actions

**Type:** Potentially breaking — applications with strict CSRF configuration

The `delete` action has been added to `FormProtection`'s `unlockedActions` list alongside `sign` and `save`. This is required because `delete` is called via JavaScript/AJAX without a CakePHP form token. If your application relied on CSRF/form protection being active for `delete`, review your security posture accordingly.

---

## Summary of required changes

| # | Area | Action required |
|---|------|----------------|
| 1 | Migration | Run `bin/cake migrations migrate -p CakeDC/Uppy` |
| 2 | Config PHP | Rename `Uppy.S3.contants` → `Uppy.S3.constants` |
| 3 | Config PHP | Remove the `endpoint` key from `Uppy.S3.config` |
| 4 | Config PHP | Remove `filter_var()` wrappers (cosmetic, not required) |
| 5 | JavaScript | Read `data.key` from `sign()` response and store it |
| 6 | JavaScript | Use stored `serverKey` as `path` in `save()` payload |
| 7 | PHP / Auth | Override `getCurrentUserId()` if using non-standard auth |
| 8 | PHP / Auth | Review admin/multi-user workflows against new ownership checks |
| 9 | PHP Entity | Replace mass-assignment of `path`, `adapter`, `hash`, etc. with explicit property assignment |
