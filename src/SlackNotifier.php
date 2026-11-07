<?php

declare(strict_types=1);

namespace Azanumer;

/**
 * Dependency-free Slack notifier over an incoming webhook URL.
 *
 *   $slack = new SlackNotifier('https://hooks.slack.com/services/T.../B.../...');
 *   $slack->message('Deploy finished in 42s.');
 *
 * Rich messages use Block Kit blocks; the plain-text fallback is always
 * sent too, so notifications still read fine in email digests and on
 * clients that don't render blocks.
 *
 * Throws SlackNotifierException on transport failure, on a non-2xx HTTP
 * status, or when Slack answers with an error string (e.g. invalid
 * payload). A 429 response is retried once after the Retry-After delay.
 */
class SlackNotifierException extends \RuntimeException
{
}

class SlackNotifier
{
    private string $webhookUrl;
    private ?string $channel = null;
    private ?string $username = null;
    private ?string $iconEmoji = null;
    private int $timeoutSeconds = 10;

    public function __construct(string $webhookUrl)
    {
        if (!str_starts_with($webhookUrl, 'https://hooks.slack.com/')) {
            throw new \InvalidArgumentException(
                'Webhook URL must start with https://hooks.slack.com/'
            );
        }
        $this->webhookUrl = $webhookUrl;
    }

    public function setChannel(string $channel): static
    {
        $this->channel = $channel;
        return $this;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function setIconEmoji(string $emoji): static
    {
        // Slack wants the colon-wrapped form, e.g. ':rocket:'.
        $this->iconEmoji = str_starts_with($emoji, ':') ? $emoji : ':' . $emoji . ':';
        return $this;
    }

    public function setTimeout(int $seconds): static
    {
        if ($seconds < 1) {
            throw new \InvalidArgumentException('Timeout must be at least 1 second.');
        }
        $this->timeoutSeconds = $seconds;
        return $this;
    }

    /**
     * Send a plain-text message. Returns true on success.
     */
    public function message(string $text): bool
    {
        return $this->send(['text' => $text]);
    }

    /**
     * Send a message with Block Kit blocks. $fallback is the plain-text
     * version shown where blocks can't render.
     *
     * @param array<int, array<string, mixed>> $blocks
     */
    public function blocks(string $fallback, array $blocks): bool
    {
        return $this->send(['text' => $fallback, 'blocks' => $blocks]);
    }

    /**
     * Send a message with legacy attachments (fields layout).
     *
     * @param array<int, array<string, mixed>> $attachments
     */
    public function attachments(string $fallback, array $attachments): bool
    {
        return $this->send(['text' => $fallback, 'attachments' => $attachments]);
    }

    /**
     * Convenience: a green/red status message for deploys, cron jobs and
     * monitors. $ok = true sends :white_check_mark:, false sends :x:.
     */
    public function status(string $title, bool $ok, string $detail = ''): bool
    {
        $emoji = $ok ? ':white_check_mark:' : ':x:';
        $text  = $emoji . ' ' . $title;
        if ('' !== $detail) {
            $text .= "\n" . $detail;
        }
        return $this->message($text);
    }

    /**
     * Low-level send. $payload is merged with the configured channel /
     * username / icon and JSON-encoded. Returns true on success.
     */
    public function send(array $payload): bool
    {
        if (null !== $this->channel) {
            $payload['channel'] = $this->channel;
        }
        if (null !== $this->username) {
            $payload['username'] = $this->username;
        }
        if (null !== $this->iconEmoji) {
            $payload['icon_emoji'] = $this->iconEmoji;
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (false === $body) {
            throw new SlackNotifierException('Failed to JSON-encode the payload.');
        }

        $attempts = 0;
        do {
            $attempts++;
            $result = $this->post($body);
            if (429 === $result['status'] && 1 === $attempts) {
                // Slack rate-limited us: wait the requested delay, then retry once.
                $delay = (int) ($result['retry_after'] ?? 1);
                sleep(max(1, min($delay, 60)));
                continue;
            }
            if ($result['status'] < 200 || $result['status'] >= 300) {
                throw new SlackNotifierException(
                    'Slack returned HTTP ' . $result['status'] . ': ' . $result['body']
                );
            }
            if ('ok' !== trim($result['body'])) {
                throw new SlackNotifierException('Slack error: ' . $result['body']);
            }
            return true;
        } while ($attempts < 2);

        throw new SlackNotifierException('Slack rate limit persisted after retry.');
    }

    /**
     * @return array{status: int, body: string, retry_after: string|null}
     */
    private function post(string $body): array
    {
        $ch = curl_init($this->webhookUrl);
        if (false === $ch) {
            throw new SlackNotifierException('Failed to initialise cURL.');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_HEADER         => true,
        ]);

        $response = curl_exec($ch);
        if (false === $response) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new SlackNotifierException('cURL error: ' . $error);
        }
        $status     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers    = substr($response, 0, $headerSize);
        $payload    = substr($response, $headerSize);
        curl_close($ch);

        $retryAfter = null;
        if (preg_match('/^Retry-After:\s*(\d+)/mi', $headers, $m)) {
            $retryAfter = $m[1];
        }

        return ['status' => $status, 'body' => $payload, 'retry_after' => $retryAfter];
    }
}
