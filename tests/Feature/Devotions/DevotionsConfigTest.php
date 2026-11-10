<?php

namespace Tests\Feature\Devotions;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** config/devotions.php */
class DevotionsConfigTest extends TestCase
{
    private const ENV = 'DEVOTIONS_AI_PROVIDER';

    protected function tearDown(): void
    {
        $this->setEnv(null);
        parent::tearDown();
    }

    private function setEnv(?string $value): void
    {
        if ($value === null) {
            putenv(self::ENV);
            unset($_ENV[self::ENV], $_SERVER[self::ENV]);

            return;
        }

        putenv(self::ENV . '=' . $value);
        $_ENV[self::ENV] = $value;
        $_SERVER[self::ENV] = $value;
    }

    /** Evaluate the config file fresh (the loaded config is cached at boot). */
    private function freshConfig(): array
    {
        return require config_path('devotions.php');
    }

    public static function providers(): array
    {
        return [
            'not set'              => [null, 'anthropic'],
            'empty, as in .env.example' => ['', 'anthropic'],
            'groq'                 => ['groq', 'groq'],
        ];
    }

    #[DataProvider('providers')]
    public function test_the_ai_provider_defaults_to_anthropic(?string $env, string $expected): void
    {
        $this->setEnv($env);

        $this->assertSame($expected, $this->freshConfig()['ai']['provider']);
    }

    public function test_languages_switches_and_limits(): void
    {
        $config = config('devotions');

        $this->assertSame('en', $config['default_locale']);
        $this->assertSame(['tw', 'gaa', 'ee', 'ha'], array_keys($config['translations']));
        $aiOn = array_keys(array_filter($config['translations'], fn (array $language) => $language['ai_translation'] === true));
        $this->assertSame(['ha'], $aiOn);   // only Hausa may be AI-drafted for now
        $this->assertSame([250, 350], $config['limits']['ai']['reflection_words']);
        $this->assertSame([40, 80], $config['limits']['ai']['prayer_words']);
        $this->assertSame(140, $config['limits']['ai']['thought_chars']);
        $this->assertSame([200, 450], $config['limits']['staff_warning']['reflection_words']);
        $this->assertSame(30, $config['scripture']['max_verses']);
    }
}
