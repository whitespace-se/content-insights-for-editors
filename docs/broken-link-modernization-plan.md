# Broken-link modernization and external-review design

Status: implemented on `codex/internal-broken-link-checker`. The branch starts
from production CIFE `1.2.3` (`e3edd840ac9cff1cd2ae89ed6d5494d5073001ac`).
No SKR or Höör dependency lock is changed in this repository.

## Runtime boundary

CIFE no longer depends on `helsingborg-stad/broken-link-detector`. Its scheduled
scanner extracts HTTP(S) links from configured content and Modularity fragments,
but it never makes HTTP requests for link validation.

- Same-site links are resolved from WordPress data.
- Published posts and pages are reachable; known non-published content is broken.
- Previously resolved WordPress permalinks are remembered locally, so deletion
  or a permalink change can be reported on a later scan without fetching it.
- Local upload URLs are checked against the upload filesystem.
- Unknown internal custom routes are indeterminate and are not reported as
  broken. Integrations can resolve them through
  `cife_broken_links_internal_url_status`.
- External links are inventoried for manual review and are never requested by
  CIFE.

Matomo remains an independent, explicitly configured API integration. ACF and
WordPress cron remain prerequisites used by the rest of CIFE.

## Preserved contracts

The following existing ACF settings remain unchanged:

| Purpose | Field name |
| --- | --- |
| Analyzed content types | `analysed_post_types` |
| Municipio main-editor ownership | `use_alternate_user_field` |
| Scheduled summary email | `use_broken_links_cron` |
| Outdated-content threshold | `ws_minimum_last_updated_threshold` |
| Matomo connection | `matomo_api_url`, `matomo_api_key`, `matomo_id_site` |
| Optional feedback threshold | `ws_minimum_feedback_responses` |

Internal broken results continue to use the existing
`{$wpdb->prefix}broken_links_detector` table. It is adopted in place and is not
dropped on deactivation, preserving the existing overview, counts, metabox,
dashboard widgets, email section, and rollback data.

Ownership continues to mean `post_author`, or `page_meta_maineditor` for pages
when the alternate-user setting is enabled. Non-administrators can only see and
review links on content they own; administrators see all content.

The analyzed-content selector only contains standalone public content types.
Media (`attachment`), Municipio external-page records (`external_page`), modal
content (`modal-content`), and revisions are excluded from both new and existing
saved selections. Modularity content—including modal modules—is extracted as a
fragment of the parent page instead of being scanned and reported separately.

## External-link data and review lifecycle

The migration is additive:

```text
{$wpdb->prefix}cife_external_links
  id, url, url_hash, first_seen, last_seen, checked_at, checked_by

{$wpdb->prefix}cife_external_link_occurrences
  id, link_id, post_id, first_seen, last_seen

{$wpdb->prefix}cife_internal_link_targets
  id, url, url_hash, post_id, last_seen
```

The URL is a shared record and each page containing it is an occurrence. Marking
a URL checked therefore records one shared `checked_at` date while retaining
links to every affected page. Removed occurrences and orphaned URL records are
cleaned up after a page completes scanning.

The new ACF option `cife_external_link_review_days` defaults to 30 days. A URL is
due when either its `first_seen` date (never checked) or `checked_at` date is
older than the interval. Newly discovered links receive the full initial grace
period. Due links appear in administrator/editor dashboard widgets and in the
existing manual and scheduled email summaries.

The **External links** admin screen opens targets only through a normal user
click (`target="_blank"` with `noopener noreferrer`). Marking a link checked is a
nonce-protected POST and also verifies ownership/capability server-side.

## Scheduling and migration

- CIFE owns a daily scan hook and bounded continuation hook.
- The old `broken-links-detector-external` hook is cleared; CIFE never defers to
  the old package even if its class happens to be loaded.
- Scan state holds internal and external results until the current page is
  complete, then replaces both per-page result sets.
- Deleted or newly ineligible content is cleaned from both stores.
- Existing CIFE settings and internal result rows require no data copy.
- Version `2.4.2` includes the external-link workflow and responsive settings
  and metabox presentation for the staging iteration.

## Release sequence

1. Run unit tests and PHP/Composer validation locally.
2. Run the WordPress integration suite and rehearse the additive migration in a
   disposable production-like environment.
3. Deploy this branch to SKR staging and trigger a full scan.
4. Verify internal results, external inventory visibility, ownership rules,
   mark-checked behavior, dashboard widgets, email content, cron continuation,
   Matomo, outdated content, and optional customer feedback.
5. Only after staging acceptance, tag/release CIFE and update Municipio/SKR/Höör
   locks in separately reviewed changes.

Rollback keeps the legacy internal table and existing settings intact. The new
external tables are ignored by older CIFE versions.
