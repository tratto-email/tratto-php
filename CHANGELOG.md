# Changelog

All notable changes to `tratto/email` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [1.1.0] - 2026-10-20

### Added

- `emails->sendBatch()`: sends 1 to 100 emails in one request, `POST /v1/emails/batch`, with one optional
  idempotency key for the whole batch. Requires API 0.22.0. A 202 with `failed` greater than 0 is returned,
  not thrown. `scheduledAt` and `attachments` are not accepted in a batch: the API answers 422.
- `TrattoException::$details`: the `details` of the API error as it is, for example the list of
  `{path, message}` of a 422, or `null`. It is the last constructor parameter, so existing calls are unchanged.

### Fixed

- `Client::VERSION`, sent in the `User-Agent`, said `1.0.0` in the 1.0.1 release.

## [1.0.1] - 2026-10-14

### Fixed

- README and `examples/`: placeholder ids now use the prefixes the API generates (`tmpl_`, `email_`,
  `cont_` instead of `tpl_`, `em_`, `con_`). Documentation only, no code change.

## [1.0.0] - 2026-10-14

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
