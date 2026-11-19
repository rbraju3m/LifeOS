# ADR 0008: Private S3-compatible object storage

- Status: Accepted (2026-11-18)
- Blueprint: [§16](../blueprint/16-file-management-architecture.md)

## Decision
- User files are stored in a private S3-compatible bucket (Cloudflare R2, Wasabi or DigitalOcean Spaces, in or near Singapore).
- Object keys contain no filenames.
- Downloads use presigned URLs that expire after 5 minutes, issued only after an authorization check.
- Uploads go through the API in the MVP.

## Alternatives
**Local disk:** creates backup and scaling problems.

## Consequences
- Files stay off the VPS disk, durability is high and storage is cheap. R2 has no egress fees.
- Backups need a replica with a second provider.

## Migration risk
Low, because storage goes through Laravel's Flysystem abstraction.
