# Operations and maintenance

## Access and processing

Members use core WordPress password hashes/cookies plus bounded portal sessions, verified email, owner-only records and reauthentication for sensitive actions. Staff use WordPress sign-in with Two-Factor TOTP/backup codes; a password-only staff session is denied. Editors have no lead/report access. Assignment never automatically grants medical access.

Enquiries have encrypted payloads, a durable reference and idempotency keys; queued mail retries are separate from acceptance. Staff can assign, record notes/status/follow-ups and close with an approved reason. Queue filters include service, status, explicit language, unassigned and overdue; paging is 100 records. Bulk export is administrator-only, reauthenticated, rate-limited and audited; it contains safe queue fields without names, phone/email, free text, internal notes or medical data.

Report versions are separate encrypted uploads with an owner-validated reference to the original; original content is never silently changed. Owner search/filter terms stay in authenticated POST bodies. Search processes bounded batches of 100 encrypted records; large-account latency and pagination capacity need benchmarking. Timeline entries link only own records.

Sharing selects specific available reports and frozen profile sections for the currently assigned named staff member, with 1–30 day expiry. Revoke, reassignment, enquiry closure, consent withdrawal or deletion stops future reads. A previously downloaded copy cannot be recalled.

Exceptional support is disabled unless its procedure is approved. An administrator with dedicated support capability, MFA and fresh reauthentication may request up to five specific records for a validated issue reference and limited reason for 15 minutes. Every scope is audited; owner history/revoke controls exist. Creation currently uses protected `POST /wp-json/iaai/v1/staff/support-access` with `owner_id`, `issue_ref`, `reason_code` and selected report IDs; there is no ordinary-staff support-access creation form. An operational issue-verification and notification procedure is required.

## Backups and restore

Back up the database, private encrypted files, schema version, managed code/templates and separately protected encryption secret. Keep keys outside code/database exports. Restrict access and document actual backup retention/expiry. Do not put backup archives in a public web directory.

Restore into a separate protected environment using synthetic data first. Verify authenticated decryption, owner and selected-recipient reads, denied cross-owner reads, quarantine processing, page mappings, scheduled worker, mail and deletion behavior. Record who tested it and when before marking restore readiness. No host backup restore was performed in this session.

A code-release rollback uses owned-code backups and page snapshots only. A disaster restore is a separately reviewed operation with a known data-loss window; never use it automatically to repair a failed code release.

## Deletion and exports

Account export requires fresh identity confirmation, is built into encrypted private storage and is downloadable by the owner for 24 hours. It includes private profile, reports/originals, timeline and safe enquiry information. A comprehensive all-purpose regulatory export of every consent/access/audit history is not implemented; do not represent this as a compliance certification.

Record deletion revokes online reads immediately; the worker physically purges encrypted files and adjusts quota. Account deletion restricts sessions/sharing first, then erases own online records and the WordPress user. Enquiry business data is unlinked and retained according to the separately approved procedure. Backups age out by the hosting retention policy, not by a promise of instant removal.

Check every day: queued/failed jobs, recent worker heartbeat, free private-storage capacity, real scanner freshness, mail failures and safe audit references. Resolve scan errors without exposing files publicly. Keep report processing temp storage private and investigate stale job leases.

## Updates

Preserve manual Elementor edits with reconciliation; export and review changes before merging source. For every dependency update, update the official URL/checksum lock, run the synthetic suite in both modes and repeat native kit/snippet import tests on staging. Review backward-compatible schema migrations. Deploy through the checked workflow after the initial staging rehearsal.

No production data, real keys, passwords, patient filenames or raw enquiry messages belong in Git, page templates, mail notifications, analytics or routine logs. The included fixtures are tiny synthetic files, not medical examples or real antivirus samples.
