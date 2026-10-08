# Host configuration

Use a separate staging installation first. These are examples, not credentials or verified Hostinger paths. Do not set `IAAI_DEV_MODE` or `IAAI_TEST_SITE` on production.

## Runtime and private storage

PHP 8.3 with mysqli, sodium, mbstring, fileinfo, gd, zip, dom and curl; MariaDB/MySQL transactional InnoDB tables; HTTPS; authenticated WordPress sessions; Two-Factor TOTP for privileged staff. File processing requires `proc_open`, ClamAV, qpdf and pdftoppm. WP-CLI and SSH are needed for automatic deployment and minute-based scheduled jobs.

Create a 0700 directory outside `ABSPATH` and all public web roots, writable only by the PHP/worker account. Files use random keys, 0600 permissions and authenticated encryption. The portal refuses a private root beneath WordPress. Protect private backups and scratch processing directories with the same access policy.

Configure host-owned values before WordPress boots:

```php
// Example placement: wp-config.php BEFORE wp-settings.php. Replace examples on the host.
// Read the encryption secret from separately protected host configuration.
define('IAAI_ENCRYPTION_KEY', getenv('IAAI_ENCRYPTION_KEY'));
define('IAAI_PRIVATE_ROOT', '/ABSOLUTE/PRIVATE/DIRECTORY');
define('IAAI_CLAMSCAN_BIN', '/usr/bin/clamscan');
define('IAAI_QPDF_BIN', '/usr/bin/qpdf');
define('IAAI_PDFTOPPM_BIN', '/usr/bin/pdftoppm');
define('IAAI_SCANNER_UPDATED_AT', getenv('IAAI_SCANNER_UPDATED_AT'));
define('DISABLE_WP_CRON', true); // ONLY after the replacement scheduler works.
```

`IAAI_ENCRYPTION_KEY` must be base64 of exactly 32 cryptographically random bytes. Generate and store it on the host in an approved secret store. Never put it in Git, snippets, page templates, email or public environment logs. Back up the key separately from the protected database/files; losing it prevents recovery.

This release uses one encryption key and fixed authenticated envelope version. **Keyring-based online rotation is not implemented.** Do not replace the key in place while encrypted data exists. Rotation requires a maintenance migration that decrypts/verifies with the old key, re-encrypts and verifies with the new key, and retains protected restore material. Such a migration is not included or tested.

## Scan and preview pipeline

Upload limits: one to five files per submission, 10,000,000 bytes each; proposed per-account quota 250,000,000 bytes. Configure `upload_max_filesize`, `post_max_size` and `max_file_uploads` accordingly. Start with at least 1 GB worker memory for full private exports and benchmark the actual plan before accepting large account archives.

Only detected PDF/JPEG/PNG with matching extensions are admitted to encrypted quarantine. ClamAV must scan decrypted private scratch files before availability. Keep actual signatures updated; update the host-controlled freshness timestamp only after a successful signature refresh. Freshness expires after seven days. A timestamp alone is not a malware scan or proof of installed signatures.

qpdf structurally checks PDFs and rejects encrypted/active content and excessive page counts. pdftoppm renders bounded safe image previews for each accepted page. Images are decoded/flattened by GD; original bytes remain encrypted and immutable. Security/processor failure leaves the record unreadable. No OCR, diagnosis or insurance decision is generated.

## Worker, notices and collection gates

Run `wp --path=/ABSOLUTE/WORDPRESS iaai jobs` every minute through the hosting scheduler with a single worker account. Verify job processing and that readiness reports a heartbeat within five minutes. WordPress cron is only a fallback. Configure real mail transport and a genuine staff notification inbox; notification messages contain references/service identifiers, not full enquiry or medical text.

`wp iaai health` reports readiness booleans; the public `/wp-json/iaai/v1/health` exposes safe version/release/schema status only. The Setup page requires a protected staff session. Owner approvals represent completed business notice review, retention policy, real mail delivery and documented restore drill; they are not substitutes for those activities.

## Cache and security configuration

Exclude `/account/`, `/my-dashboard/`, `/my-profile/`, `/my-reports/`, `/my-treatment-history/`, `/my-enquiries/`, `/account-settings/`, `/wp-json/iaai/v1/*` and signed-in WordPress cookies from Hostinger/CDN/page/object response caches. Honor `private, no-store`, noindex and authorization headers. Never cache authenticated report bytes. Test through the real CDN with separate user sessions; local HTTP header checks do not verify upstream cache behavior.

Keep HTTPS and trusted proxy configuration correct; do not trust arbitrary forwarded IPs or origins. Do not embed analytics in protected portal routes. The code adds no marketing analytics or third-party social embeds. Keep PHP error display off in production and restrict operational logs.

## Remaining integration boundaries

Hostinger plan capabilities, actual mail delivery, AV signatures, scheduler, backup restore, upstream caches, capacity and credential recovery are unverified. Business retention durations are deliberately not invented. Automated deletion covers online private records, expired grants/tokens/exports and account requests. Business enquiry/audit retention and backup expiry need an approved schedule and an operational purge/expiry procedure; a general age-based lead/audit/backup deletion scheduler is not included.
