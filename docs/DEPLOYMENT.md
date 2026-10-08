# Repository and deployment

Source repository: https://github.com/dev-desire/itsallaboutinsurance, created by the owner with public visibility. No live custom-code release has been deployed. Hostinger connection and deployment secrets remain unconfigured.

## Repository setup

Canonical source, tests, lock files, layouts and workflow belong in this repository. Production data and secrets must remain outside Git. `dist/` is generated and ignored; GitHub checks build it from source. Use `main` for production and `develop` for a separate staging site. Initial owner-approved WordPress/plugin/theme installation must exist before the automatic updater runs.

The workflow has read-only repository permissions, immutable action commits, environment-based deployment, per-target concurrency with cancellation disabled, a MariaDB service, PHP/dependency checks, native kit import, manual-edit/rollback checks, plugin/snippet parity, safe synchronization and HTTP permission/cache-header checks. Pull requests run checks without deployment. This workflow has not run on GitHub yet.

## GitHub environments

Configure `production` and `staging` independently. Deployment is skipped until the repository Actions variable `DEPLOY_ENABLED` is set to `true`; enable it only after initial installation and verified hosting configuration. CI checks still run on pushes and pull requests.

| Type | Name | Value |
|---|---|---|
| Secret | `DEPLOY_HOST` | Actual verified SSH hostname |
| Secret | `DEPLOY_PORT` | Actual SSH port |
| Secret | `DEPLOY_USER` | Dedicated hosting deploy account |
| Secret | `DEPLOY_SSH_KEY` | Private deploy key scoped to the intended host/account |
| Secret | `DEPLOY_KNOWN_HOSTS` | Trusted host-key entry verified with the hosting operator |
| Variable | `DEPLOY_WP_PATH` | Absolute existing WordPress path |
| Variable | `DEPLOY_ROOT` | Absolute private release/backup path outside the web root |
| Variable | `SITE_URL` | `https://itsallaboutinsurance.com` for production; separate staging URL |

Do not populate known_hosts by blindly trusting the first connection. The scripts require strict host verification; missing secrets or invalid paths fail closed. A root-domain HTTPS site is supported by the current deploy URL validator; subdirectory WordPress deployment is not supported by this adapter.

## Release behavior

`deploy.sh` packages only the two owned code archives and checksums, uploads through verified SSH and runs `host-release.sh`. The host locks deployment, checks the site's configured URL, verifies archive paths/checksums and backs up only IAAI plugin/theme code outside the web root.

It enters maintenance mode, swaps the two managed directories, synchronizes only owned snippets in snippet mode, reconciles separately managed pages and runs worker/health checks. It then exits maintenance and checks real public English/Hindi pages, release health and anonymous denial/no-store before committing the release marker.

User records, the WordPress database, uploads, private medical storage, keys, unrelated plugins and unrelated snippets are never copied from the repository. Manual Elementor edits remain untouched when an incoming layout is unchanged. When both layout source and an owner-edited page change, reconciliation fails with a conflict and preserves the owner edit.

Resolve conflicts on staging: export the real owner edit, merge it into canonical template source, regenerate the manifest and review the result. No force-overwrite flag is provided. The same rule applies to manual edits of managed snippets.

## Failed release and rollback

A nonzero failure before release commitment invokes targeted template rollback and restores the previous managed code folders. Snippet mode re-synchronizes the restored package. The previous release option is restored and maintenance is removed. A whole-database backup is never restored over current user data.

Template snapshots include prior post fields, Elementor data/settings, metadata description and managed mapping. Rollback restores a page only when its current hash still matches the deployed snapshot; subsequent owner edits are reported as conflicts. New unchanged pages from a failed release are removed. Any migration in later releases must remain backward compatible with old code; this updater does not reverse database schema migrations.

Local real-WordPress targeted rollback and command-double host-script failure cases passed. Actual SSH, filesystem permissions, host caches and production rollback still need a staging rehearsal. Review failed job logs and the host release directory; do not delete private code backups until the approved retention window.

## Snippet mode

Keep the IAAI support plugin installed for assets/classes. Install the locked Code Snippets version. In plugin mode:

```bash
wp iaai sync-snippets
# Review inactive generated code on staging, then activate only managed wrappers.
wp iaai sync-snippets --activate
```

Add `define('IAAI_RUNTIME_MODE','snippets');` before WordPress boots only after this succeeds. Verify `/wp-json/iaai/v1/health` and the security checks. The plugin then does not boot its own handlers; checksum-verified snippet bootstrap is the only runtime. Missing/wrong modules stop the snippet runtime.

Native JSON imports are inactive. Import the complete matching set once, review it, and use the synchronizer for subsequent managed updates. Synchronization adopts exact matching imports, preserves their IDs, detects manual code conflicts and leaves unrelated snippets alone. Recover syntax problems by restoring known-good owned code through the host and choosing plugin mode; the snippets are thin callbacks into that support package. Never execute unreviewed PHP received through an arbitrary import.
