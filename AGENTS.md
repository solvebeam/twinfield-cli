# Twinfield CLI

A Composer package with the `twf` command: it logs in to Twinfield and stores the login, so tests, examples and scripts get an access token with `Credentials::default()`.

## The command is `twf`

- `twf` is the name of the command, after the `twf.` prefix Twinfield uses itself (`twf.organisationCode`). `twinfield` is the same command under its long name.
- Write `twf` in the README, the help text, docblocks, examples and tests: `vendor/bin/twf login`. Name `twinfield` only where the long name itself is explained.
- `bin/twf` holds the entry point. `bin/twinfield` only requires `bin/twf`; do not put logic there.
- `bin/twf` passes the script name to `CredentialsException::calledAs()` before it runs the application.
- Do not hard-code the command in a message. Build it with `CredentialsException::command('login --manual')`, so the message names the command the way the user called it (`vendor/bin/twf`, `vendor/bin/twinfield`, or `twf` on the PATH). Outside the command line tool it says `vendor/bin/twf`. The help text follows the call too, with `CredentialsException::commandName()`.

## Commands

- `twf` is a Symfony Console application, like Composer: `Application` extends the one of `symfony/console`.
- Each command is a class in `src/Command/` that extends `SolveBeam\Twinfield\Cli\Command\Command`. That class gives it the `CredentialStore`, the `--profile` option and `profileName()`. Register a new command in `Application`.
- Write the classic style, `#[AsCommand]` with `configure()` and `execute()`: the package supports `symfony/console` 6.4, 7 and 8, so it fits in the project of the user.
- Write output with `OutputInterface::OUTPUT_RAW`, so a `<` in a value is not read as a formatting tag.
- Throw an exception for an error. `Application` prints it as one line, `Error: …`, with exit status 1; `-v` shows the trace.

## Checks

Run all three before you commit; CI runs them on PHP 8.3, 8.4 and 8.5.

```sh
composer phpcs    # PSR-12, also for bin/twf and bin/twinfield
composer phpstan  # level 8
composer phpunit
```

## Tests

- Tests do not use the network and do not open a browser. Test a login with `--manual` and a pasted redirect URL.
- Run a command with the `ApplicationTester` of Symfony, see `ApplicationTest::twf()`.
- Extend `SolveBeam\Twinfield\Cli\Tests\TestCase`: it gives each test its own `TWINFIELD_CONFIG_DIR` and clears the `TWINFIELD_*` environment variables.

## Credentials file

- Change `credentials.json` only through `CredentialStore`: it locks the file, keeps it at mode 0600 and leaves it as it was when a change fails.
- Never print or log a refresh token or a client secret. `twf token` prints an access token because that is what it is for.
