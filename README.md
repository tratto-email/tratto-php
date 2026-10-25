# tratto/email

Official PHP SDK for [Tratto](https://tratto.email/en?utm_source=packagist), an email API hosted in the EU:
transactional email, campaigns and automation flows from one REST API. Every provider is listed on the
[sub-processors page](https://tratto.email/en/sub-processors?utm_source=packagist).

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**Links:** [Documentation](https://docs.tratto.email) ·
[API reference](https://docs.tratto.email/en/docs/api-reference-intro) ·
[Node.js SDK](https://www.npmjs.com/package/@tratto/email) ·
[Python SDK](https://pypi.org/project/tratto-email/) ·
[Changelog](CHANGELOG.md)

## Installation

```bash
composer require tratto/email
```

Requires **PHP 8.1 or later** with the `curl` extension. There are no other runtime dependencies.

## Quick start

```php
use Tratto\Client;

$tratto = new Client((string) getenv('TRATTO_API_KEY'));

$email = $tratto->emails->send([
    'from' => 'Acme <hello@mail.acme.com>',
    'to' => 'user@example.com',
    'subject' => 'Welcome!',
    'html' => '<p>Thanks for signing up.</p>',
]);

echo $email['id'];
```

Or write the email in [emailmd](https://www.emailmd.dev/) markdown: the API renders it into responsive,
email-safe HTML (plus a text part). `markdown` and `html` are mutually exclusive.

```php
$tratto->emails->send([
    'from' => 'Acme <hello@mail.acme.com>',
    'to' => 'user@example.com',
    'subject' => 'Welcome!',
    'markdown' => "# Welcome, {{firstName}}\n\nGlad to have you on board.",
    'variables' => ['firstName' => 'Alice'],
]);
```

Keep the key out of your code: read it from the environment or your secret store. API keys are created in
the dashboard, under API Keys.

## Setup

```php
$tratto = new Tratto\Client($apiKey, [
    'baseUrl' => 'https://api.tratto.email', // default
    'timeout' => 30,                         // seconds, default 30 (connection: at most 10)
]);
```

| Option | Type | Description |
|---|---|---|
| `baseUrl` | `string` | `http://` or `https://` URL, without `/v1` (the SDK adds it). With `http://` the key travels in clear text: use it only for a local server. |
| `timeout` | `int\|float` | Total time per request, in seconds. |
| `transport` | `Tratto\Http\Transport` | Replaces the built-in curl transport, for example with a fake in your tests. See [Custom transport](#custom-transport). |

An empty key, an unknown option or an invalid value throws `\InvalidArgumentException`. There is no
option to pass curl settings: TLS verification is always on and redirects are never followed.

## Responses

Methods return the decoded JSON as associative arrays, so a field added by the API is available without
updating the SDK.

- Single objects come without the `data` envelope: `$tratto->emails->get($id)['status']`.
- Paginated lists return the whole page, `['data' => [...], 'pagination' => ['hasMore' => bool, 'nextCursor' => ?string]]`:
  pass `nextCursor` as `after` to read the next page.
- Methods that answer 204 (`webhooks->delete`, `templates->delete`) return nothing.
- Dates you pass (`\DateTimeInterface`, in parameters or query strings) are sent in UTC with milliseconds;
  your object is not modified.
- Ids are URL-encoded, so an id can never change the route.

## Test mode

Every workspace can create test keys (`tratto_test_...`) next to live ones. A test key runs the same
pipeline (statuses, timeline, webhooks) but nothing is delivered: no domain verification needed, no
monthly quota used. Test sends have their own limit of 100 emails per day.

| Recipient | Outcome |
|---|---|
| any address | `delivered` |
| `bounced@simulator.tratto.email` | permanent bounce, the email ends `bounced` |
| `soft-bounced@simulator.tratto.email` | transient bounce |
| `complained@simulator.tratto.email` | spam complaint |

Email responses and webhook payloads carry `livemode: false`. A test key sees only test data. Calls that reach
real recipients are rejected for test keys with `403 TEST_MODE_NOT_SUPPORTED`: campaign send, campaign
test send, template test send and flow activation.

## Resources

### Emails

```php
// Idempotency: retrying with the same key never sends twice.
$email = $tratto->emails->send([
    'from' => 'hello@mail.acme.com',
    'to' => ['alice@example.com', 'bob@example.com'],
    'subject' => 'Your receipt',
    'templateId' => 'tmpl_abc123',
    'variables' => ['name' => 'Alice'],
    'scheduledAt' => new DateTimeImmutable('+1 hour'),
], 'receipt-order-1234');

$page = $tratto->emails->list(['status' => 'delivered', 'limit' => 20]);
$detail = $tratto->emails->get('email_abc123');
$events = $tratto->emails->listEvents('email_abc123');
```

`emails->send` and `emails->sendBatch` take an idempotency key. A key containing a line break is rejected before sending.

A failed email carries `failureReason` (for example `template_not_found` or `attachment_unavailable`), the same
code as `data.reason` of the `failed` webhook event. The set is open: handle unknown values.

#### Batch

`emails->sendBatch()` sends 1 to 100 emails in one request (requires API 0.22.0). Each email takes the
fields of `send()` except `scheduledAt`: the API rejects the batch with a 422 whose
`details` name the email, for example `/emails/3/to`. One idempotency key covers the whole batch.

```php
$emails = [
    ['from' => 'hello@mail.acme.com', 'to' => ['alice@example.com'], 'subject' => 'Hi Alice', 'html' => '<p>Hi</p>'],
    ['from' => 'hello@mail.acme.com', 'to' => ['bob@example.com'], 'subject' => 'Hi Bob', 'html' => '<p>Hi</p>'],
];
$batch = $tratto->emails->sendBatch($emails, 'newsletter-2026-10-20');

// $batch: ['batchId' => 'batch_...', 'livemode' => true, 'accepted' => 2, 'failed' => 0,
//          'emails' => [['id' => 'email_...', 'status' => 'queued', 'to' => [...]], ...]]
```

The response always has status 202, even when some emails failed: check `failed`. Results are in the order
of the request, and a failed email has no `id` but an `error` with `code` and `message`. Resend only the
failed ones, in a new batch with a **new** key (the same key would replay the same response):

```php
$failed = [];
foreach ($batch['emails'] as $i => $result) {
    if ($result['status'] === 'failed') {
        $failed[] = $emails[$i];
    }
}
if ($failed !== []) {
    $tratto->emails->sendBatch($failed, 'newsletter-2026-10-20-retry-1');
}
```

After a 503 or a 500 nothing was sent and the key is released: retry the same batch with the same key.

### Attachments

Requires `tratto/email` 1.2.0 or later, and a paid plan (the Free plan answers 403 `PLAN_UPGRADE_REQUIRED`).
Upload the file first, then reference it by id from `emails->send()` or `emails->sendBatch()`. A full
workspace storage answers 429 `QUOTA_EXCEEDED`: delete attachments you no longer need.

```php
$file = $tratto->attachments->upload(file_get_contents('invoice.pdf'), 'invoice.pdf', 'application/pdf');
// or a stream, read from its current position: fopen('invoice.pdf', 'rb')

$tratto->emails->send([
    'from' => 'billing@mail.acme.com',
    'to' => ['alice@example.com'],
    'subject' => 'Your invoice',
    'html' => '<p>Attached.</p>',
    'attachments' => [['id' => $file['id']]],
]);

$meta = $tratto->attachments->get($file['id']); // metadata only, never the bytes
$tratto->attachments->delete($file['id']);
```

The bytes are sent as they are with the content type you give, never JSON-encoded. Non-ASCII filenames are
supported. Live attachments expire (`expiresAt`); an email queued with an attachment that was deleted or
expired fails with `failureReason` `attachment_unavailable`.

### Contacts

```php
$contact = $tratto->contacts->create(['email' => 'alice@example.com', 'firstName' => 'Alice', 'tags' => ['vip']]);
$tratto->contacts->update($contact['id'], ['status' => 'unsubscribed']);

// q is a case-insensitive prefix search on the email: compare the address on the results.
// It combines with status only.
$page = $tratto->contacts->list(['q' => 'ann@example.com']);

// CSV import: pass the content, not a path. It is sent as text/csv and validated by the API.
$job = $tratto->contacts->importCsv(file_get_contents('contacts.csv'));
$status = $tratto->contacts->getImportJob($job['jobId']);
```

### Audiences

```php
$audience = $tratto->audiences->create([
    'name' => 'Power users',
    'rules' => [['field' => 'tags', 'operator' => 'array_contains', 'value' => 'vip']],
]);
$tratto->audiences->list(['limit' => 20]);
$tratto->audiences->get($audience['id']);
$result = $tratto->audiences->addContacts($audience['id'], ['cont_1', 'cont_2']); // 1 to 500 ids
```

### Campaigns

```php
$campaign = $tratto->campaigns->create([
    'name' => 'June newsletter',
    'templateId' => 'tmpl_abc123',
    'audienceId' => 'aud_abc123',
    'fromName' => 'Acme',
    'fromEmail' => 'news@mail.acme.com',
    'subjectA' => 'Our June update',
]);

$tratto->campaigns->testSend($campaign['id'], 'me@example.com');
$tratto->campaigns->send($campaign['id']);                                                   // now
$tratto->campaigns->send($campaign['id'], ['scheduledAt' => new DateTimeImmutable('2027-03-01 09:00', new DateTimeZone('Europe/Rome'))]);

$tratto->campaigns->unschedule($campaign['id']); // back to draft; 409 once the send has started
$tratto->campaigns->pause($campaign['id']);      // stops a send in progress
$stats = $tratto->campaigns->getStats($campaign['id']);
$tratto->campaigns->list(['status' => 'scheduled']);
$tratto->campaigns->get($campaign['id']);
```

### Templates

```php
// With "markdown" the SDK adds format "emailmd" for you.
$template = $tratto->templates->create(['name' => 'Welcome', 'markdown' => '# Hi {{name}}']);
$tratto->templates->update($template['id'], ['markdown' => '# Hello {{name}}']); // a template keeps its format: markdown stays markdown

$versions = $tratto->templates->listVersions($template['id']);
$v2 = $tratto->templates->getVersion($template['id'], 2);

$tratto->templates->testSend($template['id'], 'me@example.com', ['name' => 'Alice']);
$tratto->templates->list(['status' => 'published']);
$tratto->templates->get($template['id']);
$tratto->templates->delete($template['id']);
```

### Webhooks

```php
$webhook = $tratto->webhooks->create([
    'url' => 'https://my-app.example.com/webhooks/tratto',
    'events' => ['delivered', 'bounced', 'complained'],
]);
// $webhook['secret'] is returned only now: store it in your secret store, never in code or logs.

$tratto->webhooks->list();
$tratto->webhooks->listDeliveries($webhook['id'], ['limit' => 20]);
$tratto->webhooks->test($webhook['id']);
$rotated = $tratto->webhooks->rotateSecret($webhook['id']);
$tratto->webhooks->delete($webhook['id']);
```

The `failed` event (an email that was not sent) is delivered only to webhooks that list it in `events`. Its
`data` is `['reason' => ..., 'error' => ...]`; `reason` is the same code as `failureReason` on
`emails->get()`. `Tratto\Webhook::EVENT_TYPES` lists every event type.

### Domains

```php
$domain = $tratto->domains->add('mail.acme.com');   // includes the DNS records to publish
$tratto->domains->verify($domain['id']);
$tratto->domains->list();
$tratto->domains->get($domain['id']);
$deleted = $tratto->domains->delete($domain['id']); // ['id' => ..., 'deletedAt' => ...]
```

### Analytics

```php
$summary = $tratto->analytics->getSummary('30d');
$points = $tratto->analytics->getTimeseries('7d');
```

Periods: `7d`, `30d` (default), `90d`, `180d`, `1y`. `180d` and `1y` hold live data only and are rejected
for test keys.

### Flows

```php
$flow = $tratto->flows->create(['name' => 'Welcome series']);
$tratto->flows->update($flow['id'], [
    'trigger' => ['type' => 'contact_tag_added', 'config' => ['tagName' => 'signed-up']],
    'steps' => [
        ['id' => 'step_1', 'type' => 'send_email', 'config' => ['templateId' => 'tmpl_welcome']],
        ['id' => 'step_2', 'type' => 'wait', 'config' => ['delay' => '2', 'unit' => 'days']],
    ],
]);
$tratto->flows->activate($flow['id']);
$tratto->flows->deactivate($flow['id']);
$tratto->flows->list();
$tratto->flows->get($flow['id']);
$tratto->flows->delete($flow['id']); // ['id' => ...]
```

Config values are strings, and an unknown config key is ignored without an error: a trigger without
`tagName` enrolls contacts on any tag change, a `wait` without `delay` and `unit` waits 5 minutes. The
accepted keys for each trigger and step type are declared on `Flows::update()`, so PHPStan checks them.

> **Flows need an API key with the `*` permission.** There are no `flows:read` or `flows:write` scopes.
> Create a key for the job and revoke it when you are done. `activate` is rejected for test keys.

### Workspace

```php
$workspace = $tratto->workspace->get();
$tratto->workspace->update(['name' => 'Acme Corp', 'timezone' => 'Europe/Rome']);
$tratto->workspace->updatePreferences(['locale' => 'en']);
```

Members are invited, changed and removed from the dashboard: this SDK has no member methods.

## Errors

Every failed API call throws `Tratto\TrattoException`: HTTP errors, responses that are not JSON, and
network errors (`errorCode` `connection_error`, `statusCode` 0). Configuration mistakes throw
`\InvalidArgumentException`.

```php
use Tratto\TrattoException;

try {
    $tratto->emails->send($params);
} catch (TrattoException $e) {
    error_log(sprintf('[%d] %s: %s', $e->statusCode, $e->errorCode, $e->getMessage()));
    // $e->suggestion: what to do about it, when the API says
    // $e->docs: a link to the documentation of this error, when available
}
```

| Property | Type | |
|---|---|---|
| `getMessage()` | `string` | The API message, or `HTTP <status>` when the response has none |
| `errorCode` | `string` | The API error code, `unknown_error`, `invalid_response`, `invalid_header` or `connection_error` |
| `statusCode` | `int` | The HTTP status (also `getCode()`), 0 when no response was received |
| `suggestion` | `?string` | |
| `docs` | `?string` | |
| `details` | `?array` | The `details` of the API error as they are, for example a 422's list of `['path' => ..., 'message' => ...]`; `null` when absent |

The SDK does not retry. To retry a send safely, pass the same idempotency key. A `sendBatch` where only some
emails failed is not an exception: it returns the 202 response with `failed` greater than 0 (see [Batch](#batch)).

### PHP 8.1: do not log full exception traces

On PHP 8.2 and later the key and the webhook secret are marked `#[\SensitiveParameter]` and never appear
in the SDK's own traces. PHP 8.1 ignores that attribute: the SDK moves them out of its parameters before doing anything
else, but an argument of the wrong type (for example an array where a string is expected) raises a
`TypeError` before that, and the trace of that error holds the arguments. On PHP 8.1, log the
message, `errorCode` and `statusCode` of SDK exceptions, not `getTraceAsString()` or a dump of the
exception, or set `zend.exception_ignore_args=On`.

## Webhook verification

Each delivery carries `X-Tratto-Signature: t=<unix ms>,v1=<hex>`. `Tratto\Webhook::verify()` checks the
HMAC-SHA256 of `{t}.{raw body}` with your secret (`whsec_...`, used as it is), accepts any of several `v1`
values (secret rotation), and rejects deliveries older or newer than the tolerance (default 5 minutes).
It returns `bool` and never throws for a bad signature.

Always pass the **raw** body: a decoded and re-encoded JSON will not match.

```php
use Tratto\Webhook;

$payload = file_get_contents('php://input');
$header = $_SERVER['HTTP_X_TRATTO_SIGNATURE'] ?? null;

if (!Webhook::verify($payload, $header, (string) getenv('TRATTO_WEBHOOK_SECRET'))) {
    http_response_code(400);
    exit;
}

$event = json_decode($payload, true);
// Deliveries can repeat: use $event['id'] to process each event once.
```

With Laravel (route in `routes/api.php`, which has no CSRF middleware; on Laravel 11 or later create it with
`php artisan install:api`):

```php
public function __invoke(Illuminate\Http\Request $request)
{
    $signature = $request->header('X-Tratto-Signature');
    if (!Webhook::verify($request->getContent(), is_string($signature) ? $signature : null, (string) config('services.tratto.webhook_secret'))) {
        return response('', 400);
    }
    // ...
}
```

The tolerance is the fourth argument, in seconds: `Webhook::verify($payload, $header, $secret, 600)`.

## Notes

- **Empty objects.** In PHP `[]` is both an empty list and an empty object. The SDK sends the bodies it
  builds correctly (`templates->testSend` always sends `variables` as an object, `{}` when empty), but in
  the arrays you pass, an empty nested array goes out as a JSON array. For fields that are objects, such as
  `variables` or `headers` in `emails->send`, omit the field or pass `new \stdClass()`.
- **Custom transport.** <a id="custom-transport"></a>A `transport` must implement
  `Tratto\Http\Transport` and throw only `Tratto\TrattoException` (with `errorCode` `connection_error`
  when no response was received). The SDK re-throws those without their trace. Any other exception
  leaves your transport as it is, and its trace includes the request headers, API key included.
- **Dates.** A `\DateTimeInterface` is sent in UTC; a string is sent as it is.

## Examples

See [`examples/`](examples/): one file per resource, a plain PHP webhook endpoint, and Laravel and
Symfony integrations. Every example is analysed by PHPStan and PHP-CS-Fixer in CI, so a renamed method
breaks the build instead of your integration. The key always comes from the environment:

```bash
TRATTO_API_KEY=tratto_test_... php examples/send-email.php
```

| File | |
|---|---|
| [`send-email.php`](examples/send-email.php) | HTML, markdown, template, idempotency key, timeline |
| [`contacts.php`](examples/contacts.php) | Create, update, search, CSV import |
| [`audiences.php`](examples/audiences.php) | Rule-based audiences, adding contacts |
| [`campaign.php`](examples/campaign.php) | Create, test, schedule, unschedule, pause |
| [`templates.php`](examples/templates.php) | Markdown templates, versions, test send |
| [`domains.php`](examples/domains.php) | Add a domain, DNS records, verify |
| [`webhooks.php`](examples/webhooks.php) | Register, test, delivery history, rotate the secret |
| [`webhook-endpoint.php`](examples/webhook-endpoint.php) | Receive and verify webhooks in plain PHP |
| [`flows.php`](examples/flows.php) | Automation flows (key with the `*` permission) |
| [`analytics.php`](examples/analytics.php) | Summary and timeseries |
| [`workspace.php`](examples/workspace.php) | Workspace settings and preferences |
| [`laravel/`](examples/laravel/) | Service provider binding `Tratto\Client`, webhook controller |
| [`symfony/`](examples/symfony/) | Service definition, a service using the client, webhook controller |

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer check
```

`scripts/staging-smoke.php` runs one real round trip against the staging API with a test key; it refuses
live keys and the production URL. See the comment at the top of the script.

## License

MIT
