<?php

declare(strict_types=1);

require __DIR__ . '/../src/SlackNotifier.php';

use Azanumer\SlackNotifier;
use Azanumer\SlackNotifierException;

$webhook = getenv('SLACK_WEBHOOK_URL');
if (false === $webhook || '' === $webhook) {
    fwrite(STDERR, "Set SLACK_WEBHOOK_URL to your incoming webhook URL first.\n");
    exit(1);
}

$slack = (new SlackNotifier($webhook))
    ->setUsername('Deploy Bot')
    ->setIconEmoji('rocket');

try {
    // 1. Plain message.
    $slack->message('Deploy of mysite.com finished in 42s.');

    // 2. Status helper (green check / red cross).
    $slack->status('Nightly backup', true, 'Database + uploads, 1.2 GB, kept 7 days.');

    // 3. Block Kit message with a plain-text fallback.
    $slack->blocks('New order #1042 — $89.00', [
        [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "*New order #1042*\nCustomer: Jane Doe — *\$89.00*",
            ],
        ],
        [
            'type' => 'actions',
            'elements' => [
                [
                    'type' => 'button',
                    'text' => ['type' => 'plain_text', 'text' => 'View order'],
                    'url'  => 'https://example.com/wp-admin/post.php?post=1042&action=edit',
                ],
            ],
        ],
    ]);

    echo "All three messages sent.\n";
} catch (SlackNotifierException $e) {
    fwrite(STDERR, 'Slack failed: ' . $e->getMessage() . "\n");
    exit(1);
}
