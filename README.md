# Twinfield CLI

Twinfield CLI brings Twinfield to your terminal. Free and open source.

Log in to Twinfield once from the command line, then use that login in tests, examples and scripts:

```sh
composer require --dev solvebeam/twinfield-cli
vendor/bin/twf login
```

```php
use SolveBeam\Twinfield\Cli\Credentials;
use SolveBeam\Twinfield\Twinfield;

$twinfield = Twinfield::create(Credentials::default());

foreach ($twinfield->offices()->list() as $office) {
    echo $office->code, ' ', $office->name, PHP_EOL;
}
```

It works like `gcloud auth application-default login`, `aws sso login` or `gh auth login`: the command line tool stores the login, and your code picks it up without knowing about OAuth.

You do not need a Twinfield client ID: by default the login goes through [Twinfield Connect](https://github.com/solvebeam/twinfield-connect-php). The first login registers the Twinfield CLI with Twinfield Connect once per machine.

## When to use it

- Tests and examples that call the live Twinfield API.
- Scripts you run yourself, such as an import or a report.
- CI, with environment variables instead of the login (see below).

In an application where users log in themselves, use [solvebeam/twinfield-connect](https://github.com/solvebeam/twinfield-connect-php) or [solvebeam/oauth2-twinfield](https://github.com/solvebeam/twinfield-oauth2-php) and store the refresh tokens yourself.

## Commands

```sh
vendor/bin/twf login           # log in with your browser
vendor/bin/twf login --manual  # paste the redirected URL yourself, e.g. over SSH
vendor/bin/twf login --rest    # also ask for all REST API scopes
vendor/bin/twf status          # show the logins
vendor/bin/twf token           # print a valid access token, e.g. for curl
vendor/bin/twf logout          # remove the login
vendor/bin/twf office list     # list the offices
vendor/bin/twf list            # show all commands
vendor/bin/twf login --help    # show the options of a command
```

The command is `twf`, after the `twf.` prefix Twinfield uses itself (`twf.organisationCode`). `vendor/bin/twinfield` is the same command under its long name.

`login` opens the Twinfield login in your browser and catches the redirect on `http://localhost:8765/callback`.

### Offices

`office list` lists the offices (companies, administrations) you have access to, like `gh repo list`:

```sh
vendor/bin/twf office list                # all offices
vendor/bin/twf office list '*Holding*'    # search in code and name: * matches anything, ? one character
vendor/bin/twf office list --limit=10     # or -L 10
vendor/bin/twf office list --json         # JSON, e.g. for jq
vendor/bin/twf office list --profile=acme # the offices of another login
```

In a terminal the offices are a table. In a pipe they are tab-separated lines without a header, with the code, name, country and company ID:

```sh
vendor/bin/twf office list | cut -f1
```

### Profiles

Use a profile for each organisation:

```sh
vendor/bin/twf login --profile=acme
```

```php
$twinfield = Twinfield::create(Credentials::default('acme'));
```

Or choose the profile with `TWINFIELD_PROFILE=acme`.

### Your own Twinfield client

```sh
vendor/bin/twf login --client-id=… --client-secret=… --redirect-uri=http://localhost:8765/callback
```

The redirect URI must be registered with your client. Instead of the options you can set `TWINFIELD_CLIENT_ID`, `TWINFIELD_CLIENT_SECRET` and `TWINFIELD_REDIRECT_URI`.

## Where the login is stored

In `~/.config/twinfield/credentials.json`, or in `$XDG_CONFIG_HOME/twinfield/` or `$TWINFIELD_CONFIG_DIR/` when set. Only you can read the file (0600).

The file holds the refresh token and the last access token. `Credentials::default()` uses the access token until it expires, then refreshes it and writes the new tokens back. Writes lock the file, so processes that run at the same time do not overwrite each other's refresh token.

## CI and environment variables

When `TWINFIELD_REFRESH_TOKEN` is set, `Credentials::default()` uses it instead of the file. `Credentials::default('acme')` always uses the profile you name.

| Variable | |
|---|---|
| `TWINFIELD_REFRESH_TOKEN` | The refresh token. |
| `TWINFIELD_CONNECT_CLIENT_ID`, `TWINFIELD_CONNECT_CLIENT_SECRET` | The Twinfield Connect client, see `credentials.json`. |
| `TWINFIELD_CLIENT_ID`, `TWINFIELD_CLIENT_SECRET` | Or your own Twinfield client. |
| `TWINFIELD_CONNECT_URL` | Only for a Twinfield Connect you run yourself. |
| `TWINFIELD_REDIRECT_URI` | Only when your client does not use `http://localhost:8765/callback`. |

Tokens from environment variables are not written back.

## License

The MIT License (MIT). See [LICENSE](LICENSE).
