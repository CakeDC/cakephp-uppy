# Multipart S3 uploads

Direct-to-S3 multipart uploads for large files using [Uppy](https://uppy.io/docs/aws-s3/) v5 `AwsS3` and the plugin signing endpoints.

PHP never receives file bytes — the browser uploads parts directly to S3 using presigned URLs.

## Endpoints

Base path: `/uppy/files/`

All endpoints accept `POST` with a JSON body and return JSON.

| Action | Route | Purpose |
|---|---|---|
| `sign` | `/uppy/files/sign` | Single presigned PUT (small files) |
| `createMultipartUpload` | `/uppy/files/create-multipart-upload` | Start multipart upload |
| `signPart` | `/uppy/files/sign-part` | Presign one part |
| `completeMultipartUpload` | `/uppy/files/complete-multipart-upload` | Finish multipart upload |
| `abortMultipartUpload` | `/uppy/files/abort-multipart-upload` | Cancel multipart upload |

### createMultipartUpload

**Request**

```json
{
  "filename": "video.mp4",
  "contentType": "video/mp4",
  "prefix": "alerrt/ResourceFiles",
  "filesize": 524288000
}
```

**Response**

```json
{
  "error": false,
  "code": 200,
  "uploadId": "...",
  "key": "alerrt/ResourceFiles/uuid-video-mp4"
}
```

### signPart

**Request**

```json
{
  "uploadId": "...",
  "key": "alerrt/ResourceFiles/uuid-video-mp4",
  "partNumber": 1
}
```

**Response**

```json
{
  "error": false,
  "code": 200,
  "url": "https://...",
  "headers": {}
}
```

### completeMultipartUpload

**Request**

```json
{
  "uploadId": "...",
  "key": "alerrt/ResourceFiles/uuid-video-mp4",
  "parts": [
    { "PartNumber": 1, "ETag": "\"abc123\"" }
  ]
}
```

**Response**

```json
{
  "error": false,
  "code": 200,
  "location": "https://bucket.s3.region.amazonaws.com/..."
}
```

### abortMultipartUpload

**Request**

```json
{
  "uploadId": "...",
  "key": "alerrt/ResourceFiles/uuid-video-mp4"
}
```

## Configuration

In `config/uppy.php` (host application):

```php
'Uppy' => [
    'MaxFileSize' => 1073741824,
    'MultipartThreshold' => 104857600,
    'S3' => [
        'constants' => [
            'lifeTimePutObject' => '+20 minutes',
            'lifeTimeUploadPart' => '+20 minutes',
        ],
    ],
],
```

- `MaxFileSize` — when set, `filesize` in sign/create requests is validated server-side (`null` = no limit).
- `MultipartThreshold` — default for client-side `shouldUseMultipart` (100 MiB); exposed via `UppyHelper::getUploadConfig()` and `window.UppyUploadConfig`.
- `lifeTimeUploadPart` — presigned URL lifetime for each upload part.

## S3 CORS

The bucket must allow browser uploads from your admin origin. Example rule:

```json
{
  "AllowedOrigins": ["https://admin.example.com"],
  "AllowedMethods": ["GET", "PUT", "POST", "HEAD"],
  "AllowedHeaders": ["Authorization", "content-type", "x-amz-date", "x-amz-content-sha256"],
  "ExposeHeaders": ["ETag", "Location"],
  "MaxAgeSeconds": 3000
}
```

`ETag` must be exposed so Uppy can complete multipart uploads.

## Uppy client example

```javascript
const uploadConfig = window.UppyUploadConfig || {};
const multipartThreshold = uploadConfig.multipartThreshold ?? 100 * 1024 * 1024;

uppy.use(AwsS3, {
  shouldUseMultipart: (file) => file.size > multipartThreshold,
  async createMultipartUpload(file) {
    const response = await fetch('/uppy/files/create-multipart-upload', {
      method: 'POST',
      headers: {
        accept: 'application/json',
        'content-type': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      body: JSON.stringify({
        filename: file.name,
        contentType: file.type,
        prefix: 'alerrt/ResourceFiles',
        filesize: file.size,
      }),
    });
    const data = await response.json();
    if (data.error) throw new Error(data.message);
    return { uploadId: data.uploadId, key: data.key };
  },
  async signPart(file, { uploadId, key, partNumber }) {
    const response = await fetch('/uppy/files/sign-part', {
      method: 'POST',
      headers: {
        accept: 'application/json',
        'content-type': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      body: JSON.stringify({ uploadId, key, partNumber }),
    });
    const data = await response.json();
    if (data.error) throw new Error(data.message);
    return { url: data.url, headers: data.headers || {} };
  },
  async completeMultipartUpload(file, { uploadId, key, parts }) {
    const response = await fetch('/uppy/files/complete-multipart-upload', {
      method: 'POST',
      headers: {
        accept: 'application/json',
        'content-type': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      body: JSON.stringify({ uploadId, key, parts }),
    });
    const data = await response.json();
    if (data.error) throw new Error(data.message);
    return { location: data.location };
  },
  async abortMultipartUpload(file, { uploadId, key }) {
    await fetch('/uppy/files/abort-multipart-upload', {
      method: 'POST',
      headers: {
        accept: 'application/json',
        'content-type': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      body: JSON.stringify({ uploadId, key }),
    });
  },
  getUploadParameters(file) {
    // existing single-PUT sign flow for smaller files
  },
});
```
