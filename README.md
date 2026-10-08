# It’s All About Insurance — WordPress 0.1.0

Target domain: **https://itsallaboutinsurance.com/**. Hosting: **Hostinger**.

This is an installable, tested development release. **It has not been installed on the live domain.** Source repository: https://github.com/dev-desire/itsallaboutinsurance (currently public). Elementor 4.3.4 and Two-Factor 0.17.0 are active on WordPress; the custom plugin, theme and pages are not installed. Hostinger deployment secrets have not been configured.

Start with **START-HERE-HI.md** for installation. The detailed architecture is in `docs/TECHNICAL-REQUIREMENTS.md`; actual verification and remaining acceptance work are in `docs/VERIFICATION.md`.

## Package contents

| Path | Use |
|---|---|
| `dist/iaai-portal.zip` | Upload through WordPress → Plugins → Add New → Upload Plugin |
| `dist/iaai-base.zip` | Upload through Appearance → Themes → Add New → Upload Theme |
| `elementor/iaai-website-kit.zip` | Complete native Elementor kit with IAAI blueprint runner; fresh installation only |
| `elementor/pages`, `elementor/global` | 53 individual native editable template exports and manifest |
| `snippets/*.json` | 19 inactive native Code Snippets imports: 18 module wrappers and one bootstrap |
| `snippets/*.php` | Corresponding readable wrappers; matching support plugin is required |
| `wp-content/` | Canonical plugin/theme source, assets, migration definitions and embedded managed layouts |
| `.github/workflows/wordpress.yml` | Check then deploy main→production and develop→staging; deployment gated by DEPLOY_ENABLED until the host is configured |
| `scripts/` | Deterministic build, synthetic test preparation, native kit export and verified-SSH release scripts |
| `tests/` | Synthetic integration, kit, snippet synchronization, HTTP and release-orchestration checks |
| `docs/` | Host configuration, operations, acceptance evidence and TRD |

There are **49 pages and 4 global header/footer templates**. Seven portal pages use one route shared by both languages. Six legal pages, sixteen guide pages and two case pages remain drafts. No business credentials, patient records, uploaded files, database dumps or production encryption keys are included.

## Tested dependency set

WordPress 7.1.3, PHP 8.3.6, Elementor Free 4.3.4, Two-Factor 0.17.0 and Code Snippets 3.10.2. Exact official download URLs and SHA-256 values are in `dependencies.lock.json`. PHP needs mysqli, sodium, mbstring, fileinfo, gd, zip, dom and curl. The portal requires PHP 8.3 and WordPress 7.0 or newer; other version combinations need staging validation.

Elementor Pro is not required for this kit. The IAAI theme renders the four owned global sections; the matching plugin imports all 53 canonical templates while keeping draft/private flags. The native import API was tested; the interactive importer UI and other Elementor versions still require staging review.

## Build and verification

```bash
python3 scripts/generate-templates.py
python3 scripts/build.py
```

The build produces reproducible plugin/theme ZIPs and SHA-256 checksums. Snippet wrappers reference the canonical support package and verify its module/version checksums. Plugin mode is the default. Adding `IAAI_RUNTIME_MODE=snippets` requires all managed modules and the runtime bootstrap to be synchronized and activated; it prevents plugin hooks from running in parallel.

The GitHub workflow provisions an isolated synthetic WordPress installation and runs both modes. Do not run `tests/` against a real database. Runtime keys and test database files are excluded from this package.

## Launch status

The public layouts and protected processing are implemented. Real lead collection remains closed until the actual business notices are reviewed and approved. Registration additionally requires verified mail setup. Report uploads remain closed until encryption, outside-webroot storage, scanner, PDF tools, retention approval, restore verification and the scheduled worker are ready. Existing records cannot be read through a public media URL.

Live custom-code installation, actual email delivery, malware-signature scanning, host cache isolation, backup restore, responsive/accessibility browser testing and actual main/develop deployment have not been verified. The current code is not evidence that AC01–AC30 have all passed on production.
