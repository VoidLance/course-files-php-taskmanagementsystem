<?php

declare(strict_types=1);

namespace App\Services;

class MailService
{
    public function __construct(private array $config)
    {
    }

    public function send(string $to, string $subject, string $body): void
    {
        // Local-dev "mail": write message to log instead of SMTP.
        $line = sprintf(
            "[%s] TO=%s SUBJECT=%s BODY=%s\n",
            date('c'),
            $to,
            $subject,
            str_replace(["\r", "\n"], ' ', $body)
        );

        file_put_contents($this->config['mail']['log_file'], $line, FILE_APPEND);
    }
}
