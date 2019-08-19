# Content Insights for Editors

> An easy way for content editors to keep track of their work and get notified when it is not working properly

Content Insights for Editors is a WordPress plugin 

## Table of Contents

* [Usage](#usage)
    * [Prerequisites](#prerequisites)
    * [Installation](#installation)
* [Develop](#develop)
    * [Local prerequisites](#local-prerequisites)
    * [Local installation](#local-installation)
* [Contributing](#contributing)
* [Versioning](#versioning)
* [Authors](#authors)
* [License](#license)

## Usage

### Prerequisites

* [WordPress](https://wordpress.com/)
* [ACF](https://www.advancedcustomfields.com/)
* [helsingborg-stad/broken-link-detector](https://github.com/helsingborg-stad/broken-link-detector)
* [matomo](https://matomo.org/)

Content Insights for Editors uses matomo for visitor statistics and [helsingborg-stad/broken-link-detector](https://github.com/helsingborg-stad/broken-link-detector) for analysing content.

### Installing

The package can be downloaded manually and unzipped in the /wp-content/plugins/ directory or by using composer. 

Add this to your composer.json

```
"repositories": [
    {
        "type": "vcs",
        "url": "git@github.com:whitespace-se/content-insights-for-editors.git",
        "no-api": true
    },
    ...
]

"require": {
    ...
    "content-insights-for-editors": "1.0.0"
},
```

To install helsingborg-stad/broken-link-detector

```
"repositories": [
    {
        "type": "vcs",
        "url": "git@github.com:helsingborg-stad/broken-link-detector.git",
        "no-api": true
    },
    ...
]

"require": {
    ...
    "helsingborg-stad/broken-link-detector": ">=1.0.0",
},
```

Then run 

```
composer install
```

Example of how composer works [here](https://wpackagist.org/)

When resources have been installed. The plugin can be activated by looking for a "Content Insights for Editors" entry in the plugins page and clicking on "Activate".

### Hooks

Here are the hooks for customizing the plugin

### Adding a section to mail notification
```php
cife_notification_mail_list_sections
```

**Example usage**

```php
add_action('cife_notification_mail_list_sections', function($sections){
    array_push($sections, [
        'section_header' => '', // string, Title rendered above section
        'list' => array(
            [
                'url' => '', // string, list item url
                'title' => '', // string, list item title
                'value' => 0, // mixed, Value to display in the second column (OPTIONAL)
            ],
            ...
        ),
        'list_header' => ['title' => '', 'value' => ''], // array, Explaining list.title and list.value 
        'no_items_text' => '', // string, Replace list if empty
    ]);
    return $sections;
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
add_action('cife_notification_mail_vars', function($html_vars){
    $html_vars['logo'] = "..."; 
    return $html_vars;
});
```

## Develop

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes. See [Usage](#usage) for notes on how to use the plugin in production.

### Local prerequisites

* [WordPress](https://wordpress.com/)
* [ACF](https://www.advancedcustomfields.com/)
* [helsingborg-stad/broken-link-detector](https://github.com/helsingborg-stad/broken-link-detector)
* [matomo](https://matomo.org/)

Content Insights for Editors uses matomo for visitor statistics and [helsingborg-stad/broken-link-detector](https://github.com/helsingborg-stad/broken-link-detector) for analysing content.

### Local installation

The plugin can be installed with composer or by cloning this repo from github into the /wp-content/plugins/ directory of your WordPress installation.

**With composer**

Add this to your composer.json

````
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
````

And this to install helsingborg-stad/broken-link-detector

````
"repositories": [
    {
        "type": "vcs",
        "url": "git@github.com:helsingborg-stad/broken-link-detector.git",
        "no-api": true
    },
    ...
]

"require": {
    ...
    "helsingborg-stad/broken-link-detector": ">=1.0.0",
},
````

Then run 

````
composer install
````

Example of how composer works [here](https://wpackagist.org/)

## Contributing

1. Fork it (<https://github.com/yourname/yourproject/fork>)
2. Create your feature branch (`git checkout -b feature/fooBar`)
3. Commit your changes (`git commit -am 'Add some fooBar'`)
4. Push to the branch (`git push origin feature/fooBar`)
5. Create a new Pull Request

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the [tags on this repository](https://github.com/whitespace-se/content-insights-for-editors/tags). 

## Authors

* **Johan Veeborn** and **Anders Rehn**

See also the list of [contributors](https://github.com/whitespace-se/content-insights-for-editors/graphs/contributors) who participated in this project.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details