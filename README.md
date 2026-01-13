# PlotBox PHP_CodeSniffer Standard

This repository contains the PlotBox coding standard for PHP_CodeSniffer (PHPCS).

It is packaged as a Composer-installable PHPCS standard so you can include it in any PHP project and run it via `phpcs --standard=PlotBox`.

## Install in a project

1. Require the standard in your project:

```bash
composer require --dev plotbox/standards
```

2. Ensure Composer plugins are allowed (Composer 2.2+):

```json
{
  "config": {
    "allow-plugins": {
      "dealerdirect/phpcodesniffer-composer-installer": true
    }
  }
}
```

3. Optionally, add a project-level `phpcs.xml` to select the standard by default:

```xml
<?xml version="1.0"?>
<ruleset name="Project Standard">
    <rule ref="PlotBox"/>
</ruleset>
```

## Usage

Run PHPCS using the PlotBox standard:

```bash
./vendor/bin/phpcs --standard=PlotBox path/to/src
```

To auto-fix fixable issues:

```bash
./vendor/bin/phpcbf --standard=PlotBox path/to/src
```

## What’s included

- Base PSR-12 and PSR-1 rules with PlotBox overrides
- External standards used by this ruleset are installed automatically via Composer:
  - squizlabs/php_codesniffer
  - slevomat/coding-standard
  - mediawiki/mediawiki-codesniffer
  - phpcompatibility/php-compatibility
  - escapestudios/symfony2-coding-standard
  - rarst/phpcs-cognitive-complexity
- Custom PlotBox sniffs under `src/PlotBox/Sniffs`

## Development

- Ruleset file: `src/PlotBox/ruleset.xml`
- Custom sniffs are PSR-4 autoloaded under the `PlotBox\\` namespace from `src/PlotBox/`.
- After changing sniffs, run:

```bash
composer dump-autoload
```

Then verify the standard is detected:

```bash
./vendor/bin/phpcs -i
# Look for "The installed coding standards are: ... PlotBox"
```
