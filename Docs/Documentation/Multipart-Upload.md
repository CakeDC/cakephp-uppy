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

**Response**

```json
{
  "error": false,
  "code": 200
}
```

## Configuration

Update `config/uppy.php`:

```php
return [
    'Uppy' => [
        // Maximum file size in bytes (null = unlimited)
        'MaxFileSize' => 524288000, // 500 MB

        // File size threshold for multipart (default: 100 MiB)
        'MultipartThreshold' => 104857600,

        'S3' => [
            'constants' => [
                'lifeTimeGetObject' => '+20 minutes',
                'lifeTimePutObject' => '+5 minutes',
                'lifeTimeUploadPart' => '+20 minutes', // NEW
            ],
            'config' => [
                'version' => 'latest',
                'region' => env('S3_REGION'),
                'use_path_style_endpoint' => (bool)env('S3_USE_PATH_STYLE_ENDPOINT', false), // NEW
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

## S3 CORS Configuration

Your S3 bucket must allow CORS for multipart uploads. Example CORS policy:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<CORSConfiguration xmlns="http://s3.amazonaws.com/doc/2006-03-01/">
    <CORSRule>
        <AllowedOrigin>https://yourdomain.com</AllowedOrigin>
        <AllowedMethod>GET</AllowedMethod>
        <AllowedMethod>POST</AllowedMethod>
        <AllowedMethod>PUT</AllowedMethod>
        <AllowedMethod>HEAD</AllowedMethod>
        <MaxAgeSeconds>3000</MaxAgeSeconds>
        <AllowedHeader>*</AllowedHeader>
        <ExposeHeader>ETag</ExposeHeader>
    </CORSRule>
</CORSConfiguration>
```

**Important:** The `ExposeHeader` for `ETag` is required for multipart uploads to work correctly.

## Client-Side Integration (Uppy.js)

Example using Uppy v5 with the AwsS3 plugin:

```javascript
const { Uppy } = window.Uppy;
const { Dashboard } = window.Uppy;
const { AwsS3 } = window.Uppy;

// Configuration exposed by UppyHelper
const uploadConfig = window.UppyUploadConfig || {
    maxFileSize: null,
    multipartThreshold: 104857600
};

const uppy = new Uppy({
    restrictions: {
        maxFileSize: uploadConfig.maxFileSize,
        allowedFileTypes: ['.pdf', '.png', '.doc', '.docx']
    }
})
.use(Dashboard, {
    inline: true,
    target: '#uppy-dashboard',
    proudlyDisplayPoweredByUppy: false,
})
.use(AwsS3, {
    endpoint: '/uppy/files',
    companionUrl: null,
    shouldUseMultipart: (file) => {
        // Use multipart for files above threshold
        return file.size > uploadConfig.multipartThreshold;
    },
});

uppy.on('upload-success', (file, response) => {
    console.log('Upload successful:', file.name, response);
});
```

## Driver Compatibility

| Feature | S3 | R2 | GCS |
|---------|----|----|-----|
| Simple uploads | ✅ | ✅ | ✅ |
| Multipart uploads | ✅ | ✅ | ❌ |
| Presigned URLs | ✅ | ✅ | ✅ |

**Note:** Google Cloud Storage does not support S3-compatible multipart uploads. GCS uses a different Resumable Upload API, which may be added in a future release.

## Troubleshooting

### 501 Not Implemented

If you receive a `501` error with message "Multipart uploads are not supported by the current storage driver", check that:
- Your `Uppy.driver` config is set to `'s3'` or `'r2'` (not `'gcs'`)
- The storage adapter implements `MultipartUploadAdapterInterface`

### CORS Errors

If uploads fail with CORS errors in the browser console:
- Verify your S3 bucket CORS policy includes `PUT` method
- Ensure `ExposeHeader` includes `ETag`
- Check that `AllowedOrigin` matches your application domain

### ETag Missing

If multipart completion fails with "ETag missing" errors:
- Check S3 CORS configuration includes `ExposeHeader` for `ETag`
- Verify the browser can access the `ETag` response header

## Security Considerations

- **Presigned URLs expire:** Part upload URLs expire after `lifeTimeUploadPart` (default: 20 minutes)
- **Content type validation:** Server validates MIME types against `AcceptedContentTypes`
- **File size limits:** Server enforces `MaxFileSize` if configured
- **UUID-based keys:** Storage keys include UUIDs to prevent collisions and guessing
- **No server-side file handling:** PHP never touches file bytes, reducing memory/timeout risks

## Performance Tips

- **Adjust part size:** Uppy defaults to 5-10 MiB parts. For faster connections, you can increase this
- **Parallel uploads:** Uppy uploads parts in parallel by default (configurable)
- **Threshold tuning:** Adjust `MultipartThreshold` based on your use case (lower = more reliability, higher = simpler uploads)
- **TTL balance:** `lifeTimeUploadPart` should be long enough for slow connections but not excessive for security

## Example Upload Flow

1. User selects file in browser
2. Uppy checks file size against `multipartThreshold`
3. **If below threshold:** Single presigned PUT via `/sign`
4. **If above threshold:**
   - POST to `/create-multipart-upload` → get `uploadId` and `key`
   - Split file into parts (5-10 MiB each)
   - For each part: POST to `/sign-part` → upload part to presigned URL → collect `ETag`
   - POST to `/complete-multipart-upload` with all part ETags
   - S3 assembles parts into final object
5. After upload: POST to `/save` to store metadata in database
