# Verification and acceptance status

Release 0.1.0, prepared for itsallaboutinsurance.com on 8 October 2026. **No live installation, GitHub Actions run or production deployment occurred.**

## Completed checks

- **91 real-WordPress checks passed in plugin mode; 91 passed in snippet mode**, with no failures in the final results. The JSON evidence files list every check and actual runtime versions.
- Native Elementor import created all **53** editable templates on a fresh database prefix. Draft/private flags and refusal of repeated full-kit import were verified.
- Native Code Snippets parser imported **19 inactive** wrappers. The actual synchronizer adopted matching IDs, detected owner edits, preserved unrelated snippets and recovered without duplicate managed IDs.
- Real WordPress template reconciliation preserved manual edits, restored previous targeted page versions, removed an unchanged newly created page and refused to overwrite later owner edits during rollback.
- Actual local HTTP checks covered rendered English/Hindi routes, private no-store/noindex headers, anonymous dashboard/file denial and cross-origin rejection.
- Real host-release shell orchestration passed successful/repeated deployment and injected HTTP, template and partial-swap failures using **command doubles**. Unrelated synthetic data files survived; this is not proof of real SSH or a production database rollback.
- PHP syntax passed for 70 source/export/harness files; JavaScript and shell syntax passed. Archive inspection found all 53 kit templates, no WordPress content export, and exactly 19 generated snippet JSON bundles in the plugin.

Tests used PHP 8.3.6, WordPress 7.1.3, MariaDB 10.11.14, Elementor 4.3.4, Two-Factor 0.17.0 and Code Snippets 3.10.2. Fixtures contain synthetic text/image/PDFs only. PDF structural validation and preview rendering used actual tools. **The antivirus response was a development-only test double; real malware/signature scanning was not tested.** Actual mail transport was forced to fail locally to verify that queued notification failure does not lose the saved enquiry.

During the final run, automatic approval review blocked an Elementor outbound metadata request. The synthetic environment now disables all outgoing WordPress HTTP before dependency plugins load; the final tests ran with that block. No approval was requested to disclose metadata to that service. This local test restriction is separate from production dependency configuration.

## AC01–AC30 mapping

“Local coverage” records implementation or observed tests; it is not an overall production acceptance pass. Browser, hosting and business review requirements remain open where stated.

| AC | Local coverage | Still required |
|---|---|---|
| AC01 | Four services in public home layout; native HTML renders | Mobile first-screen/short-scroll visual review |
| AC02 | Service-specific enquiry widget/settings and preselection implemented | Click-through browser checks for all four services in both languages |
| AC03 | Durable reference, idempotent retry, conflict rejection and assigned queue tested | Real host submission/queue rehearsal |
| AC04 | Injected queue persistence failure rolls back; mail failure preserves enquiry | Host storage/transport/timeout fault rehearsal |
| AC05 | Versioned enquiry/storage consent; marketing separately optional | Approved live notice wording and evidence retention procedure |
| AC06 | Same-origin/CSRF/rate checks and server validation implemented/tested in part | Abuse/CAPTCHA escalation review and full browser invalid-input matrix |
| AC07 | Editor, anonymous, unassigned-agent and password-only staff denials tested | Live staff-role provisioning and MFA recovery |
| AC08 | Assignment, internal notes, reasoned closure and event trail tested; follow-up/queue implemented | Staff workflow usability and real UTC/timezone operation review |
| AC09 | Native public Elementor containers and editable widget presentation controls | Owner editing rehearsal in actual Elementor UI |
| AC10 | English/Hindi pages, explicit locale and localized form/error states implemented | All UI states and equivalent-link browser review |
| AC11 | Responsive CSS and dialog/focus/label behavior implemented | 360/390/768/1440px, keyboard and screen-reader verification |
| AC12 | Safe audit/reference-only notices; no analytics/social integration added | Actual mail payload/provider and any later analytics review |
| AC13 | Case/guide publication gate, required review date and paid-evidence guard tested | Genuine consent, evidence, editorial approval and correct case copy |
| AC14 | No invented numbers, stats, testimonials or credentials | Actual owner-provided contacts/identity/service authority review |
| AC15 | No social scraping or required third-party embeds | Real browser resilience/network-failure check |
| AC16 | Canonical/hreflang/private exclusion implemented; HTTP noindex tested | Live sitemap/SEO/cache review and genuine restore drill |
| AC17 | English default and explicit Hindi preference tested | Clean-browser and new-account end-to-end checks |
| AC18 | Shared login/register/recovery modal plus page fallback implemented | Mobile keyboard/focus/screen-reader review |
| AC19 | Least-privilege roles and unverified-record denial tested; registration gates implemented | Real verification delivery and registration journey |
| AC20 | Subject-bound one-use tokens, reset replay/session invalidation tested | Real email links, enumeration timing and rate-limit load tests |
| AC21 | Cross-owner list/content/edit/delete/timeline linkage denials tested | Multiple real browser sessions and upstream cache isolation |
| AC22 | Quarantine, PDF/pages/image previews, MIME mismatch, encryption/corruption rejection, quota, filters/version linkage and deletion tested | Real AV signatures/malware cases and hosting resource limits |
| AC23 | Encrypted outside-webroot files, authenticated binary serving and local no-store/anonymous denial tested | Direct host-path exposure checks and real Hostinger/CDN cache tests |
| AC24 | Optional profile, stale version, encryption and owner timeline tested | Full form-state and large-account browser/performance review |
| AC25 | Selected named sharing, frozen sections, expiry/revoke/reassignment denials tested | Actual staff/member browser workflow |
| AC26 | 49 individual Pages and four global sections; 12 registered widgets; native import/render tested | Interactive Elementor save/edit/import rehearsal |
| AC27 | Canonical source, 19 native inactive imports, mode parity and synchronization tested | Installed-host import/recovery rehearsal after version changes |
| AC28 | main/develop workflow and strict verified-SSH adapter prepared | New private repository, environments/secrets and actual staged/main commits |
| AC29 | Real template conflict preservation plus command-double unrelated-data preservation tested | Actual host deploy with synthetic accounts/reports and manual edits |
| AC30 | Targeted WordPress rollback and host-script injected failures tested | Actual SSH failed release, alert/reporting and host rollback rehearsal |

## Explicit limits

Legal pages and articles/cases are drafts until real review. Mail, hosting private storage/scanner, approved retention, scheduler and backups are unconfigured. Online encryption-key rotation, a comprehensive regulatory export of all audit/consent history, general business/audit/backup age-based purge scheduling and a graphical exceptional-support creation form are not included. Capacity/load testing, fault behavior on a lost database COMMIT acknowledgement, CSP/browser compatibility and independent penetration review remain unperformed. Do not claim all production acceptance criteria or compliance certification from the local test suite.

The raw evidence excludes keys, database rows and real medical files. `plugin-results.json` and `snippet-results.json` contain check names, runtime versions and timestamps only; operations instructions distinguish actual tests from test doubles.
