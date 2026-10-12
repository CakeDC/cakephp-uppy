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

### 3. The `endpoint` key is still honored for S3-compatible storage

**Type:** Informational — PHP application config

The `endpoint` key was removed from the default `config/uppy.php` template, but it is **still fully supported**: the entire `Uppy.S3.config` array is passed to `Aws\S3\S3Client`, so any AWS SDK option placed there — including `endpoint` — is honored.

If you use an S3-compatible store that needs a custom endpoint (MinIO, Ceph, LocalStack, etc.), **keep** the key in your `config/uppy.php`:

```php
'config' => [
    'version'    => 'latest',
    'connection' => 'real',
    'region'     => env('S3_REGION', null),
    'endpoint'   => env('S3_END_POINT', null),
    'credentials' => [
        'key'    => env('S3_KEY', null),
        'secret' => env('S3_SECRET', null),
    ],
],
```

Only remove it if you connect to Amazon S3 using the default endpoint.

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

`POST /uppy/files/save` now checks that each item's `path` value was previously issued by the server in the same session — either by a `sign()` call (single upload) or by a successful `completeMultipartUpload()` (multipart upload). Paths that were not issued by the server (e.g., paths constructed or modified on the client) are rejected immediately with:

```json
{ "error": true, "message": "Invalid or unrecognized file path" }
```

HTTP status remains 200 (JSON API contract), but `error` is `true`.

Each token is **one-time use**: once a path has been accepted by `save()`, it cannot be reused.

**Migration:** Update the JavaScript that sends the `save()` request so that `obj.path` is set to the `key` value returned by `sign()` (single upload) or by `createMultipartUpload()` / `completeMultipartUpload()` (multipart upload), not a value parsed from the S3 upload URL. Completing a multipart upload registers its key as a server-issued path, so the documented `multipart → POST /save` flow works out of the box.

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

**Impact on admin or multi-user workflows:** Ownership checks compare the file's `user_id` with the value returned by `getCurrentUserId()`. To change how the current user id is resolved (e.g., non-standard auth), set `Uppy.currentUserIdResolver` (see section 9). To grant privileged users (e.g., admins) access to files they do not own, the single-user-id resolver is not enough: set `Uppy.routes` to `false` and route `/uppy/files/*` to your own controller. The plugin's routes and the bundled `assets.php` always target `CakeDC/Uppy.Files`, so a subclass of `FilesController` in your application is never invoked for them.

---

### 8. `save()` now enforces ownership of the associated record

**Type:** Breaking — applications without per-user record scoping

When saving a file, `save()` now looks up `Uppy.Props.usersModel` to determine the ownership column on the related record (e.g., `user_id` for a `Users` model). If the related record does not belong to the currently authenticated user, the request is rejected with:

```json
{ "error": true, "message": "You are not authorized to associate files with this record" }
```

**Impact:** If you have records that legitimately belong to one user but files are attached by a different user (e.g., a shared-record workflow), the single-user-id resolver cannot express it. Set `Uppy.routes` to `false` and route `/uppy/files/*` to your own controller to implement that logic, since the plugin's routes always target `CakeDC/Uppy.Files` and a subclass is never invoked for them.

---

### 9. Non-standard auth: configure `Uppy.currentUserIdResolver`

**Type:** Potentially breaking — applications using non-standard auth

`FilesController` resolves the current user's id from:

1. `Uppy.currentUserIdResolver` — if set, a callable that receives the `ServerRequest` and returns the user id (`string`/`int`) or `null`. When set it is **authoritative** (a `null` or empty-string result means "no user").
2. The PSR-7 `identity` request attribute, set by `cakephp/authentication` middleware — **the supported source**.
3. The `Auth.userId` session key, as a fallback. Be aware that **neither `cakephp/authentication`, `AuthComponent` nor CakeDC/Users write this key**: it exists only for applications (and the plugin's own tests) that set it explicitly.

An empty string is normalized to `null` (unauthenticated). This matters because the ownership checks compare `(string)$file->user_id` against `(string)$currentUserId`, and `(string)null === ''`: without this, an empty id would match files whose `user_id` is `NULL`.

If your application does not use `cakephp/authentication` (or stores the identity under a different key), set the resolver in `config/uppy.php`. Otherwise every request is rejected: `sign`, the multipart endpoints and `index` return HTTP **401**, `view()`/`delete()` return HTTP **401** (no user) or **403** (not the owner), and `save()` returns a JSON error with HTTP **200** (`{ "error": true, "message": "You are not authorized to associate files with this record" }`):

```php
// config/uppy.php
'Uppy' => [
    'currentUserIdResolver' => fn(\Cake\Http\ServerRequest $request) =>
        $request->getSession()->read('MyCustomAuth.userId'),
],
```

> **Do not subclass `FilesController` for this.** The plugin routes (`/uppy/files/*`) and the bundled `assets.php` always target `CakeDC/Uppy.Files`, so a subclass in your application is never invoked for them. The resolver exists precisely to avoid needing a subclass.

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

### 12. `S3Trait` compatibility shim

**Type:** Informational (only if you extended `S3Trait`)

`CakeDC\Uppy\Util\S3Trait` remains available as a deprecated shim, but the storage backend was refactored into adapters:

- `S3Trait::_getS3Client()` (3.x) was renamed to `getS3Client()`. A deprecated `_getS3Client()` alias now delegates to `getS3Client()`; override `makeS3Client()` to inject a custom client.
- The multipart methods (`createMultipartUpload()`, `createPresignedUploadPart()`, `completeMultipartUpload()`, `abortMultipartUpload()`) moved out of the trait into `CakeDC\Uppy\Storage\MultipartUploadAdapterInterface`, implemented by `S3Adapter` and `R2Adapter`. Classes that extended the trait to override these methods must implement/override the adapter instead.
- New code should use `StorageAdapterInterface` / `MultipartUploadAdapterInterface` via `AdapterFactory::create()`.

### 13. `Uppy.Props.deleteFileS3` deprecated

**Type:** Informational

`Uppy.Props.deleteFileS3` is deprecated in favor of `Uppy.Props.deleteFileStorage`. The old key is still honored as a fallback when `deleteFileStorage` is not set. Update your `config/uppy.php`.

### 14. `/uppy/*` routes: only the known endpoints, always registered

**Type:** Behavior change — production endpoints

Previously the plugin registered its `/uppy/*` routes only when `debug` was `true`, so they were missing in production. They are now registered regardless of `debug`, but only for an explicit allow-list of endpoints (`sign`, `save`, the multipart endpoints, `view`, `delete`, `index`). The demo pages (`add`, `drag`) remain available only when `debug` is `true`. Set `Uppy.routes` to `false` in `config/uppy.php` to disable the endpoints entirely.

Because the endpoints are reachable in production, the controller now requires an authenticated user for `sign`, the multipart endpoints and `index` (which lists only the current user's files); `view`, `delete` and `save` keep their ownership checks. Protect the endpoints with your application's authentication/authorization as well. See `Docs/Documentation/Configure.md`.

---

## Summary of required changes

| # | Area | Action required |
|---|------|----------------|
| 1 | Migration | Run `bin/cake migrations migrate -p CakeDC/Uppy` |
| 2 | Config PHP | Rename `Uppy.S3.contants` → `Uppy.S3.constants` |
| 3 | Config PHP | Keep `Uppy.S3.config.endpoint` if you use an S3-compatible store (MinIO, etc.) |
| 4 | Config PHP | Remove `filter_var()` wrappers (cosmetic, not required) |
| 5 | JavaScript | Read `data.key` from `sign()` response and store it |
| 6 | JavaScript | Use stored `serverKey` as `path` in `save()` payload |
| 7 | Config PHP | Set `Uppy.currentUserIdResolver` if using non-standard auth |
| 8 | PHP / Auth | Review admin/multi-user workflows against new ownership checks |
| 9 | PHP Entity | Replace mass-assignment of `path`, `adapter`, `hash`, etc. with explicit property assignment |
| 10 | Config PHP | `Uppy.Props.deleteFileS3` deprecated → use `deleteFileStorage` |
| 11 | PHP / Trait | If you extended `S3Trait`: use `getS3Client()`/`makeS3Client()` and the multipart adapter interface |
| 12 | Config PHP | `/uppy/*` routes now registered in production → protect them or set `Uppy.routes` to `false` |
