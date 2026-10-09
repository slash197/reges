# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html). While the version is 0.x, a minor release may contain breaking changes.

## [0.1.0] - 2026-10-09

First release.

### Added

- Authentication against the REGES single sign-on, with access tokens reused until shortly before they expire and a `TokenStore` interface for keeping them between processes.
- Typed message builders for all 42 employee, contract and proposal operations, with the endpoint chosen by message type.
- Validation of each message against what its operation needs, reporting every missing field at once, with optional checks against a local copy of the nomenclators.
- `Envelope`, the final wire form of a message, which can be stored as JSON and sent later.
- Two-step reading of the result queue (`read`, `commit`, `consume`), with support for consumer ids.
- Nomenclator download, the employer profile, and management of employer-defined bonus types.
- Exceptions that tell a transient failure from a rejection.
- Example scripts that run every operation against the REGES test environment.

[0.1.0]: https://github.com/slash197/reges/releases/tag/v0.1.0
