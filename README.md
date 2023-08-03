# Content Insights for Editors

> An easy way for content editors to keep track of their work and get notified
> when it is not working properly

Content Insights for Editors is a WordPress plugin

## Table of Contents

- [Usage](#usage)
  - [Prerequisites](#prerequisites)
  - [Installation](#installation)
- [Develop](#develop)
  - [Local prerequisites](#local-prerequisites)
  - [Local installation](#local-installation)
- [Contributing](#contributing)
- [Versioning](#versioning)
- [Authors](#authors)
- [License](#license)

## Usage

### Prerequisites

- [WordPress](https://wordpress.com/)
- [ACF](https://www.advancedcustomfields.com/)
- [matomo](https://matomo.org/)

Content Insights for Editors uses matomo for visitor statistics.

### Installing

The package can be downloaded manually and unzipped in the /wp-content/plugins/
directory or by using composer.

Install by running the following command:

```
composer require municipio/content-insights-for-editors
```

Example of how composer works [here](https://wpackagist.org/)

When resources have been installed. The plugin can be activated by looking for a
"Content Insights for Editors" entry in the plugins page and clicking on
"Activate".

### Hooks

Here are the hooks for customizing the plugin

### Set the mail logo url

```php
cife_notification_mail_logo_url
```

**Example usage**

```php
add_action('cife_notification_mail_logo_url', function ($currentLogo) {
  $logo = get_field('logotype', 'option');
  return wp_get_attachment_url($logo['id']);
});
```

### Adding a section to mail notification

```php
cife_notification_mail_list_sections
```

**Example usage**

```php
add_action('cife_notification_mail_list_sections', function($sections){
    array_push($sections, [
        'section_header' => '', // string, Title rendered above section
        'list' => [
            [
                'url' => '', // string, list item url
                'title' => '', // string, list item title
                'value' => 0, // mixed, Value to display in the second column (OPTIONAL)
            ],
            ...
        ],
        'list_header' => ['title' => '', 'value' => ''], // array, Explaining list.title and list.value
        'no_items_text' => '', // string, Replace list if empty
    ]);
    return $sections;
});
```

### Modify which section template to use during render

```php
cife_notification_mail_render_section
```

**Example usage**

```php
add_action('cife_notification_mail_list_sections', function (
  $template,
  $sectionVars
) {
  if (
    $_sectionVars['id'] === 'most-viewed' &&
    class_exists('\CustomerFeedback\App')
  ) {
    return CONTENT_INSIGHTS_FOR_EDITORS_MAIL_TEMPLATE_PATH .
      '/partials/section-3-cols-customer-feedback.template.php';
  }
  return $template; // Absolute php file path
});
```

### Customizing content of mail notification

```
cife_notification_mail_vars
```

**Exposed variables**

```php
'logo'  // string, Logourl showed in top of email
'intro_header' // string, Title showen in top of email
'intro_text' // string, Text showen in top of email
'button_cta_text' // string, NULL to hide
'button_cta_url' // string, NULL to hide
```

**Example usage**

```php
add_action('cife_notification_mail_vars', function ($html_vars) {
  $html_vars['logo'] = "...";
  return $html_vars;
});
```

## Develop

These instructions will get you a copy of the project up and running on your
local machine for development and testing purposes. See [Usage](#usage) for
notes on how to use the plugin in production.

### Local prerequisites

- [WordPress](https://wordpress.com/)
- [ACF](https://www.advancedcustomfields.com/)
- [matomo](https://matomo.org/)

Content Insights for Editors uses matomo for visitor statistics.

### Local installation

The plugin can be installed with composer or by cloning this repo from github
into the /wp-content/plugins/ directory of your WordPress installation.

**With composer**

Add this to your composer.json

```
"repositories": [
    {
        "type": "path",
        "url": "/my/local/path/content-insights-for-editors"
    },
    ...
]

"require": {
    ...
    "content-insights-for-editors": "dev-master"
},
```

Then run

```
composer install
```

Example of how composer works [here](https://wpackagist.org/)

## Contributing

1. Fork it (<https://github.com/yourname/yourproject/fork>)
2. Create your feature branch (`git checkout -b feature/fooBar`)
3. Commit your changes (`git commit -am 'Add some fooBar'`)
4. Push to the branch (`git push origin feature/fooBar`)
5. Create a new Pull Request

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available,
see the
[tags on this repository](https://github.com/whitespace-se/content-insights-for-editors/tags).

## Authors

See the list of
[contributors](https://github.com/whitespace-se/content-insights-for-editors/graphs/contributors)
who participated in this project.

## License

This project is licensed under the MIT License - see the
[LICENSE.md](LICENSE.md) file for details

# Broken Link Detector
Detects and fixes (if possible) broken links in post_content. 

## Enable log mode 
Define the constant BROKEN_LINKS_LOG to true to enable extended logging. This will write curl messages and errors to the default logfile. 

## Bypass for domains
You can bypass checks (automatically consider valid) by adding domains to the 'brokenLinks/External/ExceptedDomains' filter. The filter requires that you provide your domains in the same format as parse_url($url, PHP_URL_HOST) returns. It's therefore recommended that you filter all your domains trough this function. 

```php
add_filter('brokenLinks/External/ExceptedDomains',function($array) {
    return array(
        parse_url("http://agresso/agresso/", PHP_URL_HOST),
        parse_url("http://qlikviewserver/qlikview/index.htm", PHP_URL_HOST),
        parse_url("http://serviceportalen/", PHP_URL_HOST),
        parse_url("http://a002163:81/login/login.asp", PHP_URL_HOST),
        parse_url("http://serviceportalen/Default.aspx", PHP_URL_HOST),
        parse_url("http://cmg/BluStarWeb/Start", PHP_URL_HOST),
        parse_url("http://surveyreport/admin", PHP_URL_HOST),
        parse_url("http://klarspraket/", PHP_URL_HOST),
        parse_url("http://guideochtips/", PHP_URL_HOST),
        parse_url("http://hbgquiz/index.php/category/?id=3", PHP_URL_HOST),
        parse_url("http://agresso/agresso/", PHP_URL_HOST),
        parse_url("http://a002490/efact/", PHP_URL_HOST),
        parse_url("http://a002064/Kurser/", PHP_URL_HOST),
        parse_url("http://a002064/kursbokning/", PHP_URL_HOST)
    ); 
}, 10);
```