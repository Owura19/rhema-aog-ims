<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Services\Sms\FakeSmsSender;
use App\Services\Sms\LogSmsSender;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * SMS sending sits behind App\Contracts\SmsSender. Drivers: 'log' (development:
 * writes to the log, reaches no phone) and 'fake' (tests: records messages).
 * A real provider is a later drop-in. Tests are forced onto 'fake' by phpunit.xml.
 */
class SmsSenderTest extends TestCase
{
    private function senderFor(string $driver): SmsSender
    {
        config(['sms.driver' => $driver]);
        $this->app->forgetInstance(SmsSender::class);

        return $this->app->make(SmsSender::class);
    }

    public function test_tests_always_use_the_fake_driver(): void
    {
        $this->assertSame('fake', config('sms.driver'));
        $this->assertInstanceOf(FakeSmsSender::class, $this->app->make(SmsSender::class));
    }

    public function test_log_driver_writes_the_message_to_the_log_and_reaches_no_phone(): void
    {
        Log::spy();
        $sender = $this->senderFor('log');

        $sender->send('+233241234567', 'Your code is 123456');

        $this->assertInstanceOf(LogSmsSender::class, $sender);
        $this->assertFalse($sender->deliversToRealPhones());
        Log::shouldHaveReceived('info')->once()->withArgs(
            fn (string $message, array $context) => $context['to'] === '+233241234567'
                && $context['message'] === 'Your code is 123456'
        );
    }

    public function test_fake_driver_records_messages(): void
    {
        $sender = $this->senderFor('fake');

        $sender->send('+233241234567', 'first');
        $sender->send('+233551234567', 'second');
        $sender->send('+233241234567', 'third');

        $this->assertTrue($sender->deliversToRealPhones());
        $this->assertCount(3, $sender->messages);
        $this->assertSame(['first', 'third'], $sender->messagesTo('+233241234567'));
        $this->assertSame('third', $sender->lastMessageTo('+233241234567'));
        $this->assertNull($sender->lastMessageTo('+233201234567'));
    }

    public function test_the_sender_is_a_singleton_so_tests_can_read_what_was_sent(): void
    {
        $this->assertSame($this->app->make(SmsSender::class), $this->app->make(SmsSender::class));
    }

    public function test_an_unknown_driver_fails_loudly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown SMS driver [carrier-pigeon]');

        $this->senderFor('carrier-pigeon');
    }
}
