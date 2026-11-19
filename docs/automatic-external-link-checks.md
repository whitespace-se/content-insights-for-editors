# Automatic checks for approved external links

CIFE inventories every external link but makes no external request by default.
Automatic checking is an installation-specific exception for hosts whose
technical owners have explicitly approved the traffic.

## Deployment configuration

Configure the exception in site-owned code that loads before CIFE, preferably
an MU plugin. Do not add customer-specific hosts to the shared CIFE plugin.

```php
add_filter('cife_external_link_check_config', function ($config) {
  $config['hosts'] = array('adda.se', 'www.adda.se');
  $config['user_agent'] =
    'CIFE-LinkChecker/1.0 (+https://dela.skr.se; mailto:dela@skr.se)';
  $config['interval_days'] = 30;
  $config['requests_per_minute'] = 30;
  return $config;
});
```

An empty host list or user agent disables the feature. Hosts are exact: no
wildcards or implicit subdomains are accepted. Only HTTPS on the standard port
is eligible. Administrators can see the effective values in the CIFE settings
screen, but cannot change this deployment-controlled approval from WordPress.

## Request behaviour

- CIFE checks only exact URLs already found in analysed, published content. It
  does not crawl the approved site or discover additional URLs.
- Each shared URL is checked at most once per configured interval, regardless
  of how many source pages contain it.
- A single background worker checks one URL at a time. Both continuations and
  requests within a redirect or fallback sequence are spaced according to the
  configured maximum request rate.
- The checker sends `HEAD` first. A `405` or `501` response permits one `GET`
  fallback capped at 64 KiB.
- WordPress safe HTTP functions validate every request. Redirects are handled
  explicitly, limited to three and followed only when the destination remains
  an exact approved HTTPS host.
- Only `404` and `410` are definitively broken. `401`, `403`, `429`, other HTTP
  errors, timeouts and network failures require manual review.
- Automatic results and timestamps are stored separately from a human review.
  A newer manual confirmation can therefore acknowledge an inconclusive or
  broken automatic result without erasing its audit history.

## Operational process

Keep the approval, agreed user agent and rate limit in the site repository or
its operational documentation. Remove a host from the configuration to stop
new requests immediately; its existing inventory and historical results remain
available to editors.
