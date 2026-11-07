# PHP Slack Notifier

Dependency-free Slack notifier over an incoming webhook URL. One class, no SDK, no Composer — drop it into any PHP project and start getting deploy, backup and error alerts in Slack.

## Usage

```php
use Azanumer\SlackNotifier;

$slack = (new SlackNotifier('https://hooks.slack.com/services/T.../B.../...'))
    ->setUsername('Deploy Bot')
    ->setIconEmoji('rocket');

$slack->message('Deploy of mysite.com finished in 42s.');

// Status helper: green check or red cross.
$slack->status('Nightly backup', true, 'Database + uploads, 1.2 GB, kept 7 days.');

// Block Kit message (plain-text fallback always included).
$slack->blocks('New order #1042 — $89.00', [
    [
        'type' => 'section',
        'text' => ['type' => 'mrkdwn', 'text' => "*New order #1042*\nCustomer: Jane Doe"],
    ],
]);
```

## Features

- **Plain text, Block Kit and legacy attachments** — `message()`, `blocks()`, `attachments()`.
- **status() helper** — one line for the "did the cron job work?" message, with ✅ / ❌ built in.
- **Honest errors** — throws `SlackNotifierException` on transport failure, non-2xx HTTP status, or a Slack error string. Nothing fails silently.
- **Rate-limit handling** — a 429 is retried once after Slack's `Retry-After` delay (capped at 60s).
- **Webhook validation** — the constructor rejects any URL that isn't `https://hooks.slack.com/`, so a typo can't leak a payload to a random server.
- **Fluent config** — `setChannel()`, `setUsername()`, `setIconEmoji()`, `setTimeout()`.

## Getting a webhook URL

Slack → your workspace → **Apps → Incoming Webhooks → Add New Webhook to Workspace** → pick a channel → copy the URL. Keep it secret like a password — anyone with it can post to your channel.

## Requirements

PHP 8.0+, cURL extension. No dependencies.

## License

MIT — see [LICENSE](LICENSE).
