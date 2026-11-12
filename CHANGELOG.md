# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-11-12

First release.

### Added

- `twf login`: log in to Twinfield with your browser, through Twinfield Connect or your own Twinfield client. `--manual` for a machine without a browser.
- `twf status`, `twf token` and `twf logout`.
- `twf office list`: list the offices, as a table, tab-separated lines or JSON.
- `twf process-xml` (`twf xml`): send a request to the XML web services of Twinfield.
- `--profile` and `TWINFIELD_PROFILE`: a login for each organisation.
- `Credentials::default()`: the stored login for the PHP Twinfield SDK, or a refresh token from environment variables in CI.
- `twinfield` as the long name of `twf`.

[Unreleased]: https://github.com/solvebeam/twinfield-cli/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/solvebeam/twinfield-cli/releases/tag/v0.1.0
