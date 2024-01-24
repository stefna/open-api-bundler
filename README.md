# OpenAPI bundler

Provides a cli command to help bundle up OpenAPI specifications in to one file 
by resolving all the external references in the schema

## Installation

> **Requires [PHP 8.2+](https://php.net/releases/)**

```bash
composer require stefna/open-api-bundler
```

## Usage

The bundler support both json files and yaml files.

### Example files

You can find examples of specifications in the [examples](examples/) folder

Each example should have these files:

* `schema.json` input schema
* `schema.dist.json` bundled output
* `schema.dist.min,json` bundled minified output

### Basic
```shell
> bundle basic/schema.json
{
	... bundle specification
}
```

### Output folder specified
```shell
> bundle basic/schema.json dist
Bundling: api.json
Writing output to: dist/schema.json
```

### Output file specified
```shell
> bundle basic/schema.json basic/schema.dist.json
Bundling: api.json
Writing output to: basic/schema.dist.json
```

### Bundle compression
```shell
> bundle basic/schema.json --compress
{... specification without whitespace ...}
```

## Usage with on sites with starburst

To add this command to starburst-cli just add `OpenApiBundleBootstrap` to your bootstrap config.

When using this with starburst you can add `BundleConfig` to your di to change the default values for the bundle
command
