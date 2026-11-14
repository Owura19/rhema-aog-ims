<?php

namespace Tests\Unit;

use App\Support\Sermons\YouTube;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class YouTubeTest extends TestCase
{
    public static function links(): array
    {
        return [
            'watch'                => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'watch, more params'   => ['https://www.youtube.com/watch?list=PL123&v=dQw4w9WgXcQ&t=42s'],
            'mobile'               => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ'],
            'short link'           => ['https://youtu.be/dQw4w9WgXcQ'],
            'short link, time'     => ['https://youtu.be/dQw4w9WgXcQ?t=90'],
            'live'                 => ['https://www.youtube.com/live/dQw4w9WgXcQ?si=abc'],
            'shorts'               => ['https://youtube.com/shorts/dQw4w9WgXcQ'],
            'embed'                => ['https://www.youtube.com/embed/dQw4w9WgXcQ'],
            'nocookie embed'       => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            'no scheme'            => ['youtu.be/dQw4w9WgXcQ'],
            'http and spaces'      => ['  http://youtube.com/watch?v=dQw4w9WgXcQ  '],
        ];
    }

    #[DataProvider('links')]
    public function test_youtube_links_give_the_video_id(string $url): void
    {
        $this->assertSame('dQw4w9WgXcQ', YouTube::videoId($url));
    }

    public static function notYouTube(): array
    {
        return [
            'empty'               => [''],
            'other site'          => ['https://vimeo.com/123456789'],
            'look-alike host'     => ['https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ'],
            'id too short'        => ['https://youtu.be/abc'],
            'id with bad char'    => ['https://youtu.be/dQw4w9WgXc!'],
            'channel page'        => ['https://www.youtube.com/@RhemaAoG'],
            'watch without v'     => ['https://www.youtube.com/watch?list=PL123'],
            'not a url'           => ['just some words'],
        ];
    }

    #[DataProvider('notYouTube')]
    public function test_anything_else_is_null(string $url): void
    {
        $this->assertNull(YouTube::videoId($url));
    }

    public function test_watch_and_thumbnail_urls(): void
    {
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', YouTube::watchUrl('dQw4w9WgXcQ'));
        $this->assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', YouTube::thumbnailUrl('dQw4w9WgXcQ'));
    }
}
