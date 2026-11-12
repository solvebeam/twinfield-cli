# Twinfield CLI

A Composer package with the `twf` command: it logs in to Twinfield and stores the login, so tests, examples and scripts get an access token with `Credentials::default()`.

## The command is `twf`

- `twf` is the name of the command, after the `twf.` prefix Twinfield uses itself (`twf.organisationCode`). `twinfield` is the same command under its long name.
- Write `twf` in the README, the help text, docblocks, examples and tests: `vendor/bin/twf login`. Name `twinfield` only where the long name itself is explained.
- `bin/twf` holds the entry point. `bin/twinfield` only requires `bin/twf`; do not put logic there.
- Do not hard-code the command in a message. Build it with `CredentialsException::command('login --manual')`, so the message names the command the way the user called it (`vendor/bin/twf`, `vendor/bin/twinfield`, or `twf` on the PATH). Outside the command line tool it says `vendor/bin/twf`.

## Checks

Run all three before you commit; CI runs them on PHP 8.3, 8.4 and 8.5.

```sh
composer phpcs    # PSR-12, also for bin/twf and bin/twinfield
composer phpstan  # level 8
composer phpunit
```

## Tests

- Tests do not use the network and do not open a browser. Test a login with `--manual` and a pasted redirect URL.
- Extend `SolveBeam\Twinfield\Cli\Tests\TestCase`: it gives each test its own `TWINFIELD_CONFIG_DIR` and clears the `TWINFIELD_*` environment variables.

## Credentials file

- Change `credentials.json` only through `CredentialStore`: it locks the file, keeps it at mode 0600 and leaves it as it was when a change fails.
- Never print or log a refresh token or a client secret. `twf token` prints an access token because that is what it is for.
