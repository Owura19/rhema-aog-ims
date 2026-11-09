<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;

/**
 * Test driver: records messages in memory so tests can read the code that was
 * "sent". Behaves like a delivering driver so the production guard doesn't trip.
 */
class FakeSmsSender implements SmsSender
{
    /** @var list<array{to: string, message: string}> */
    public array $messages = [];

    public function send(string $to, string $message): void
    {
        $this->messages[] = ['to' => $to, 'message' => $message];
    }

    public function deliversToRealPhones(): bool
    {
        return true;
    }

    /** @return list<string> */
    public function messagesTo(string $to): array
    {
        return array_values(array_map(
            fn (array $m) => $m['message'],
            array_filter($this->messages, fn (array $m) => $m['to'] === $to),
        ));
    }

    public function lastMessageTo(string $to): ?string
    {
        $messages = $this->messagesTo($to);

        return $messages === [] ? null : end($messages);
    }
}
