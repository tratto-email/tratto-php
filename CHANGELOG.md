# Changelog

All notable changes to `tratto/email` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [1.0.0] - Unreleased

First release: parity with the Node.js SDK `@tratto/email` 1.4.0.

### Added

- `Tratto\Client` with ten resources and 52 methods: `emails`, `contacts`, `audiences`, `campaigns`,
  `templates`, `webhooks`, `domains`, `analytics`, `flows`, `workspace`.
- `Tratto\Webhook::verify()` for the `X-Tratto-Signature` header: HMAC-SHA256 of `{t}.{raw body}`,
  several `v1` values accepted, tolerance in both directions (default 5 minutes).
- `Tratto\TrattoException` for every failed call, with `errorCode`, `statusCode`, `docs` and
  `suggestion`; network errors (`connection_error`) and non-JSON responses (`invalid_response`) included.
- Built-in transport on `ext-curl`: TLS verification always on, no redirects, HTTP and HTTPS only,
  30 second timeout (10 seconds to connect). A custom `Tratto\Http\Transport` can replace it.
- Idempotency key on `emails->send()`; header values with CR, LF or NUL are rejected.
- Ids are URL-encoded in paths; `\DateTimeInterface` values are sent in UTC with milliseconds.
- `templates->create()` adds `format: emailmd` when `markdown` is present; `templates->testSend()`
  always sends `variables` as a JSON object.

### Not included

- `workspace->delete()`, `inviteMember()`, `removeMember()` and `updateMember()`: members and the
  workspace itself are managed from the dashboard.
