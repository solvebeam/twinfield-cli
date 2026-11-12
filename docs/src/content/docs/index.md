---
title: Twinfield CLI
description: Twinfield CLI brings Twinfield to your terminal. Free and open source.
---

`twf` is Twinfield on the command line. Log in once, then list your offices, send XML requests, or use the same login in your PHP tests, examples and scripts.

## Installation

Twinfield CLI is a Composer package. It needs PHP 8.3 or newer.

```sh
composer require --dev solvebeam/twinfield-cli
```

Composer puts the command in `vendor/bin/twf`. `vendor/bin/twinfield` is the same command under its long name.

## Log in

```sh
vendor/bin/twf login
```

This opens the Twinfield login in your browser and stores the login on your machine. You do not need a Twinfield client ID: by default the login goes through [Twinfield Connect](https://github.com/solvebeam/twinfield-connect-php).

On a machine without a browser, for example over SSH, use [`twf login --manual`](/manual/twf_login/).

## Use it in the terminal

```sh
vendor/bin/twf status
vendor/bin/twf office list
vendor/bin/twf process-xml request.xml --office=1000
```

The [manual](/manual/twf/) has a page for each command.

## Use it in PHP

`Credentials::default()` gives the [PHP Twinfield SDK](https://php.twinfield.solvebeam.com/) the login of `twf login`. Your code does not need to know about OAuth.

```php
use SolveBeam\Twinfield\Cli\Credentials;
use SolveBeam\Twinfield\Twinfield;

$twinfield = Twinfield::create(Credentials::default());

foreach ($twinfield->offices()->list() as $office) {
    echo $office->code, ' ', $office->name, PHP_EOL;
}
```

It works like `gcloud auth application-default login`, `aws sso login` or `gh auth login`: the command line tool stores the login, and your code picks it up.

## When to use it

- Tests and examples that call the live Twinfield API.
- Scripts you run yourself, such as an import or a report.
- CI, with [environment variables](/guides/ci/) instead of the login.

In an application where users log in themselves, use [solvebeam/twinfield-connect](https://github.com/solvebeam/twinfield-connect-php) or [solvebeam/oauth2-twinfield](https://github.com/solvebeam/twinfield-oauth2-php) and store the refresh tokens yourself.

## Next steps

- [Profiles](/guides/profiles/): a login for each organisation.
- [Scripting](/guides/scripting/): output in a pipe, JSON and exit statuses.
- [Where the login is stored](/guides/credentials/).
