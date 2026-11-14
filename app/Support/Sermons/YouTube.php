<?php

namespace App\Support\Sermons;

/**
 * YouTube links → the 11-character video id, and back.
 * Accepts youtube.com/watch?v=, youtu.be/, youtube.com/live/, /shorts/, /embed/, /v/
 * and youtube-nocookie.com/embed/ (www., m. and music. hosts too); anything else is null.
 */
final class YouTube
{
    private const ID = '/^[A-Za-z0-9_-]{11}$/';

    public static function videoId(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;   // "youtu.be/abc…" pasted without a scheme
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        $host = (string) preg_replace('/^(www|m|music)\./', '', strtolower($parts['host']));
        $path = $parts['path'] ?? '';
        $id = null;

        if ($host === 'youtu.be') {
            $id = explode('/', ltrim($path, '/'))[0] ?? null;
        } elseif ($host === 'youtube.com' || $host === 'youtube-nocookie.com') {
            if (rtrim($path, '/') === '/watch') {
                parse_str($parts['query'] ?? '', $query);
                $id = is_string($query['v'] ?? null) ? $query['v'] : null;
            } elseif (preg_match('#^/(?:embed|live|shorts|v)/([^/?\#]+)#', $path, $m)) {
                $id = $m[1];
            }
        }

        return is_string($id) && preg_match(self::ID, $id) ? $id : null;
    }

    public static function watchUrl(string $videoId): string
    {
        return 'https://www.youtube.com/watch?v=' . $videoId;
    }

    public static function thumbnailUrl(string $videoId): string
    {
        return 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg';
    }
}
