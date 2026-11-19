# 16. File Management Architecture

- **Upload (MVP): proxied through the API** (multipart → validation → stream to S3).
  - Why: files are small (≤ 10 MB), validation is simple and server-controlled, and no CORS setup is needed on the bucket.
  - Alternative: presigned direct PUT to S3 (scales better and saves bandwidth, but needs a two-step finalize and post-upload validation).
  - Migration: add a `POST /files/upload-intent` + `/finalize` pair later. The schema already supports it (`status`).
- **Validation:**
  - Size checked against the entitlement limit and Nginx `client_max_body_size 12m`.
  - MIME type detected from content (finfo) and matched against the allow-list (`image/jpeg`, `image/png`, `image/webp`, `image/heic`, `application/pdf`). The extension must match the MIME type.
  - Images are re-encoded to strip EXIF/GPS metadata (privacy) using Intervention Image (optional, but on by default for photos).
  - PDFs are accepted as-is. Scanning: ClamAV daemon (clamd) on the VPS scans asynchronously → `available`/`quarantined`. Files are not downloadable until `available` (except by the uploader, with a warning, if scanning is disabled in dev).
- **Storage key:** `spaces/{space_id}/{file_ulid}`. There is no filename in the key and the bucket is private with no public ACL. Server-side encryption is enabled on the bucket (SSE-S3 or provider-equivalent).
- **Download:**
  1. `GET /files/{id}/download-url` checks the policy (the file's space plus every item that references it must be viewable; in practice, the attachment row's parent policy).
  2. It returns a **presigned GET URL with a 5-minute TTL** and `response-content-disposition=attachment; filename*=UTF-8''…` plus the right content type.
  3. Each download is audited for records.
  - For inline image previews, a TTL of 5 minutes and `inline` disposition are used for images only.
- **Ownership:** a file belongs to a space. Attaching it to an item requires the item and the file to be in the same space. Moving an item moves its files (re-keying is not required because the key is not used for authorization).
- **Quota:** `spaces.storage_used_bytes` is incremented and decremented in the same DB transaction as the `files` row changes, with nightly reconciliation against the sum.
- **Retention:** soft delete → `purge_after` = +30 days → the purge job deletes the object and the row. On account deletion, objects are deleted immediately after the grace period. Bucket versioning keeps deleted objects for 30 days (ransomware/accident recovery), then a lifecycle rule expires them.
- **Backup:** the bucket is replicated nightly to a second provider or region with `rclone` (encrypted with an rclone crypt remote). Exports live in a separate prefix with a 7-day lifecycle.
