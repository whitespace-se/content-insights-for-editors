<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks;

class LinkExtractor {
  public function extract(array $fragments, $baseUrl) {
    $urls = array();

    foreach ($fragments as $fragment) {
      if (!is_string($fragment) || trim($fragment) === '') {
        continue;
      }

      foreach ($this->extractHrefs($fragment) as $href) {
        $url = $this->normalize($href, $baseUrl);
        if ($url !== null) {
          $urls[$url] = $url;
        }
      }
    }

    return array_values($urls);
  }

  private function extractHrefs($html) {
    $hrefs = array();

    if (class_exists('\DOMDocument')) {
      $document = new \DOMDocument();
      $previous = libxml_use_internal_errors(true);
      $loaded = $document->loadHTML(
        '<?xml encoding="utf-8" ?><body>' . $html . '</body>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
      );
      libxml_clear_errors();
      libxml_use_internal_errors($previous);

      if ($loaded) {
        foreach ($document->getElementsByTagName('a') as $anchor) {
          if ($anchor->hasAttribute('href')) {
            $hrefs[] = $anchor->getAttribute('href');
          }
        }
        return $hrefs;
      }
    }

    preg_match_all(
      '/<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1/isu',
      $html,
      $matches
    );

    return isset($matches[2]) ? $matches[2] : array();
  }

  public function normalize($href, $baseUrl) {
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($href === '' || $href[0] === '#') {
      return null;
    }

    $scheme = parse_url($href, PHP_URL_SCHEME);
    if ($scheme && !in_array(strtolower($scheme), array('http', 'https'), true)) {
      return null;
    }

    if (strpos($href, '//') === 0) {
      $baseScheme = parse_url($baseUrl, PHP_URL_SCHEME);
      $href = ($baseScheme ?: 'https') . ':' . $href;
    } elseif (!$scheme) {
      $href = $this->resolveRelativeUrl($href, $baseUrl);
    }

    $fragmentPosition = strpos($href, '#');
    if ($fragmentPosition !== false) {
      $href = substr($href, 0, $fragmentPosition);
    }

    $href = str_replace(' ', '%20', $href);
    $parts = parse_url($href);
    return !empty($parts['scheme']) && !empty($parts['host']) ? $href : null;
  }

  private function resolveRelativeUrl($href, $baseUrl) {
    $base = parse_url($baseUrl);
    if (empty($base['scheme']) || empty($base['host'])) {
      return '';
    }

    $origin = $base['scheme'] . '://' . $base['host'];
    if (!empty($base['port'])) {
      $origin .= ':' . $base['port'];
    }

    if (strpos($href, '/') === 0) {
      return $origin . $href;
    }

    $path = isset($base['path']) ? $base['path'] : '/';
    $directory = preg_replace('#/[^/]*$#', '/', $path);
    $combined = $directory . $href;
    $segments = array();

    foreach (explode('/', $combined) as $segment) {
      if ($segment === '' || $segment === '.') {
        continue;
      }
      if ($segment === '..') {
        array_pop($segments);
        continue;
      }
      $segments[] = $segment;
    }

    return $origin . '/' . implode('/', $segments);
  }
}
