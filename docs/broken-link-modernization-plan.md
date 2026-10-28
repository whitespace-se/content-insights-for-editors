# Broken-link modernization audit and plan

Status: implementation and the local hardening pass are complete on
`codex/internal-broken-link-checker`. Production-site dependency locks have not
been changed. A WordPress integration-test environment and a disposable
production-like upgrade rehearsal are still required before release.

## Confirmed production baseline

This branch starts at CIFE `1.2.3`, commit
`e3edd840ac9cff1cd2ae89ed6d5494d5073001ac`.

That is the exact CIFE version currently locked on both:

- SKR's local checkout; and
- `whitespace-se/hoor.se-2024`, GitHub `prod`, checked 2026-10-27.

Both installations also resolve Broken Link Detector `1.4.0`, commit
`339f69623296068de07fef94584d8020347238c4`.

The later CIFE `master` branch is not the compatibility baseline for this work.

Versioning caveat: the `1.2.3` tag still declares `Version: 1.1.5` in the
WordPress plugin header. The release work must update that header to the actual new
version so WordPress and Composer do not present conflicting versions.

## Existing integration

CIFE does not check links. `Util\BrokenLinks` requires
`BrokenLinkDetector\App`, asks it to install/check its table, copies its public
static table name, and reads its next scheduled cron event. CIFE's reports query
the detector-owned table directly.

The overview page, broken-link filter, dashboard widgets, and broken-link email
section are gated by `class_exists('\\BrokenLinkDetector\\App')`.

There are two independent schedules:

| Purpose | Hook | Frequency | Control |
| --- | --- | --- | --- |
| Scan links | `broken-links-detector-external` | Daily | Always scheduled by the detector on a front-end `wp` request |
| Send CIFE summary email | `cife_cron_mail` | Every 14 days | ACF option `use_broken_links_cron` |

The “next scheduled check” shown by CIFE refers to the daily detector event.

## Contracts to preserve

### Existing settings

These stored ACF names and keys must remain readable and unchanged:

| Purpose | Field name | Field key |
| --- | --- | --- |
| Analyzed content types | `analysed_post_types` | `field_5d19ab0cbbc00` |
| Municipio main-editor ownership | `use_alternate_user_field` | `field_5d19cd83d7edd` |
| Scheduled summary email | `use_broken_links_cron` | `field_5d19cd83d4ea3` |
| Outdated-content threshold | `ws_minimum_last_updated_threshold` | `field_5d1c8f1232ba5` |
| Matomo connection | `matomo_api_url`, `matomo_api_key`, `matomo_id_site` | Existing keys |
| Optional feedback threshold | `ws_minimum_feedback_responses` | Existing key |

The replacement may add CIFE-prefixed schema and scan-state options. It must not
rename the fields above.

`analysed_post_types` currently limits CIFE's overview query and metabox
locations. The detector ignores it and scans almost every content type. The
internal checker should make the configured content types authoritative. If the
setting is empty during migration, it should temporarily use the detector's broad
legacy eligibility rather than silently clearing the report.

### Stored broken-link data

The detector table is:

```text
{$wpdb->prefix}broken_links_detector
  id       bigint(20) auto_increment
  post_id  bigint(20) nullable
  url      varchar(255) not null
  time     timestamp nullable
```

CIFE reads `id`, `post_id`, and `url`. Its overview and widgets count rows grouped
by `post_id`. The schema does not enforce uniqueness, although the detector tries
to store one row per `(post_id, url)`.

The first internal release should adopt this existing prefixed table in place.
It must not rename, copy, truncate, or drop the table during activation or
deactivation. This gives a zero-copy migration and a safe rollback path. A later
release can rename the table after the replacement has been proven.

The legacy `broken-links-detector-db-version` site option should be treated as
evidence of an old installation. CIFE should use its own schema-version option and
leave the old option intact for the initial release.

### CIFE query and UI behavior

Preserve:

- overview rows filtered by author/editor and configured content type;
- “show only pages with broken links” and count sorting;
- per-post metabox broken-link count;
- sitewide administrator top-ten broken-pages widget;
- editor/author “your pages with broken links” widget;
- edit-post links from overview, widgets, and email;
- manual email to selected administrators, editors, and authors;
- the existing 14-day email setting and hook;
- broken-link, outdated-content, Matomo, and optional customer-feedback email
  sections;
- display of the next scheduled link check when scheduled;
- ownership by `post_author`, or `page_meta_maineditor` for pages when the
  alternative ownership setting is enabled.

Keep these CIFE extension points:

- `cife_notification_mail_logo_url`
- `cife_notification_mail_list_sections`
- `cife_notification_mail_render_section`
- `cife_notification_mail_vars`
- `cife_posts_where_statments` (including the existing misspelling)

Broken Link Detector exposes `brokenLinks/External/ExceptedDomains`. Preserve it
as a deprecated alias for at least one release while adding a CIFE-named filter.
Sites may rely on it for intranet or protected domains.

## Observed detector behavior

The pinned detector:

- extracts absolute HTTP(S) anchor `href` values from `post_content`;
- on per-post scans, also attempts to inspect legacy Municipio Modularity module
  content;
- scans `publish`, `private`, and `password` posts, excluding attachments,
  revisions, and ACF internals;
- writes `%20` normalization back into post content;
- follows five redirects with a seven-second raw cURL request;
- disables TLS certificate verification;
- treats 2xx/3xx, 401, 406, and 413 as reachable;
- resolves known internal WordPress URLs without an HTTP request;
- truncates all results before a full scan and deletes a post's old rows before a
  per-post scan has successfully completed;
- auto-rewrites links across all post content when an internal permalink changes;
- drops its result table on plugin deactivation.

The internal checker should preserve useful compatibility—anchor discovery,
redirect handling, protected endpoint tolerance, internal-link awareness, IDNs,
exception domains, and Modularity coverage—but must not preserve unsafe behavior.
TLS verification stays enabled, scans do not mutate content, and good stored data
is not removed before replacement results are ready.

The permalink auto-rewrite is a separate high-impact content mutation. It is not
needed by CIFE's reports and should remain out of scope unless explicitly approved.

## Problems the replacement must solve

- Full scans make sequential network requests in one cron request.
- A failed full scan can leave the results table empty or partial.
- Raw cURL bypasses the WordPress HTTP API and disables TLS verification.
- Some SQL interpolates values directly; duplicates are not prevented by schema.
- Scheduled scans ignore CIFE's selected content types.
- Full scheduled scans do not reliably include Modularity module content.
- Scheduling depends on a front-end request.
- The next-run timestamp manually applies `gmt_offset`.
- Deactivation deletes report data.

The abandoned `origin/hybrid` branch is not a suitable implementation base. It
copied the old detector wholesale, retained the unsafe request and cron behavior,
introduced table-prefix/data-lifecycle defects, and added no migration tests.

## Proposed internal design

Keep the implementation in CIFE and divide it into testable services:

```text
BrokenLinks/
  Installer          adopts/creates schema; idempotent migrations
  Repository         reads and atomically replaces per-post results
  ContentProvider    eligible posts and content fragments, including Modularity
  LinkExtractor      parses and normalizes supported hrefs
  HttpClient         adapter around the WordPress safe HTTP API
  LinkChecker        classifies reachable/broken/skipped/indeterminate outcomes
  ScanRunner         bounded batches, locks, cursor, and URL-result cache
  Scheduler          recurring and continuation events; next-run display
```

Existing CIFE screens should depend on the repository/service rather than static
properties or package-presence checks.

### Checking policy

- Check HTTP(S) links. Ignore fragments and non-web schemes.
- Resolve root-relative and relative links against the post/site URL.
- Use `wp_safe_remote_head()` with bounded redirects and timeout; fall back to a
  small GET when HEAD is unsupported or blocked.
- Keep TLS verification enabled and use a CIFE user agent.
- Treat 2xx/3xx as reachable. Use a filterable compatibility allow-list for
  authentication/access responses, initially 401, 403, 405, 406, and 413.
- Treat definitive failures such as 404 and 410 as broken.
- Treat DNS, timeout, rate-limit, and transient server failures as indeterminate,
  not immediate proof that a link is broken.
- Cache normalized URL results so repeated links are not fetched once per post.
- Support the legacy exception-domain filter and a new CIFE-named filter.

### Safe scheduling and writes

- Add a CIFE-owned daily hook plus a single-event continuation hook for bounded
  batches.
- Register the schedule on activation and a dependable initialization hook.
- Store generation, cursor, lock expiry, timestamps, and summary counts in
  CIFE-prefixed options.
- Process bounded post/URL batches and continue until the generation completes.
- Replace a post's results only after its scan succeeds. Never truncate the table.
- Remove rows for deleted posts; clean up newly ineligible posts only after a
  successful scan generation.
- Retain `cife_cron_mail` unchanged.
- Use `wp_date()` and the WordPress site timezone for next-run output.
- Clear `broken-links-detector-external` only after the old package is absent. If
  both implementations are loaded, avoid dual scans and show an actionable notice.

## Migration and release sequence

1. Add a test harness and legacy fixtures representing CIFE `1.2.3`, the detector
   table, and existing ACF options.
2. Add the installer/repository and adopt the populated legacy table without
   changing rows.
3. Add extractor/checker services behind an injectable HTTP client.
4. Add the batched CIFE scheduler without clearing existing results.
5. Route the existing overview, metabox, widgets, and email through the internal
   repository; remove `class_exists` gates.
6. Retire the legacy cron only when the old package is no longer active.
7. Remove the Composer dependency and update documentation/translations.
8. Rehearse an upgrade using a disposable production-like WordPress fixture.
9. Tag/release CIFE.
10. In a later separately reviewed task, update Municipio and then SKR/Höör locks.

Rollback is safe because existing options and the original table remain intact.

## Automated tests

Use PHPUnit and an injectable HTTP client; tests must not depend on the public
network.

Unit coverage:

- extraction from valid/malformed anchors, duplicate links, relative links,
  fragments, ignored schemes, spaces, entities, and IDNs;
- normalization without modifying post content;
- HTTP classification, redirects, protected responses, HEAD-to-GET fallback,
  404/410, transient failures, and exception domains;
- configured content types and empty-setting migration fallback;
- batch cursor, lock expiry, cache reuse, and continuation scheduling.

WordPress integration coverage:

- clean schema creation and zero-loss adoption of populated legacy data;
- deactivation preserving results;
- atomic, deduplicated per-post replacement;
- failed/indeterminate checks preserving last known data;
- deleted and ineligible post cleanup;
- idempotent cron registration, conditional legacy-cron retirement, and site-time
  next-run display;
- overview, filters, metabox count, administrator/editor widgets, edit links,
  manual email, and scheduled email;
- Matomo, outdated-content, and optional customer-feedback behavior;
- `post_author` and `page_meta_maineditor` ownership;
- migration fixtures created from CIFE `1.2.3` settings.

## Decisions applied in the implementation

1. Scan published posts in the configured content types. Password-protected
   published posts remain included because WordPress stores them with `publish`
   status. This matches the overview, dashboards, Matomo, and email reports.
2. Require PHP 8.1 for the reusable CIFE package. Current Municipio installations
   already exceed that minimum.
3. Leave automatic permalink rewriting out of scope.
4. Keep the legacy broad public-post-type fallback when
   `analysed_post_types` is empty, avoiding an upgrade that silently checks no
   content.
5. Expand the adopted legacy URL column to 2048 characters without renaming or
   dropping the table.
6. Process checks in resumable URL batches, use an atomic expiring lock, cache
   definitive results for six hours, and remove stale rows only after a complete
   successful scan.
7. Batch scheduled notification recipients and schedule both mail and Matomo
   jobs on `init` rather than relying on a front-end request.
