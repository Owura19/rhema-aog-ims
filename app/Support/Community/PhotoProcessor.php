<?php

namespace App\Support\Community;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns an uploaded photo into a stored JPEG and thumbnail with GD only.
 *  - JPEG, PNG and WebP only; the size is read before decoding and anything over
 *    community.photos.max_megapixels is refused (a guard against memory bombs).
 *  - Re-encoding drops all metadata, so EXIF (including GPS) never reaches the disk.
 *  - GD can't read EXIF orientation (no exif extension): the app sends photos
 *    already rotated (expo-image-manipulator).
 *  - Transparent PNG/WebP areas become white.
 * Files go to the private 'community' disk as {yyyy}/{mm}/{uuid}.jpg and {uuid}_thumb.jpg.
 */
final class PhotoProcessor
{
    private const TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /** @return array{path: string, thumb_path: string, width: int, height: int, bytes: int} */
    public function store(UploadedFile $file): array
    {
        $source = $file->getRealPath();
        $info = $source ? @getimagesize($source) : false;

        if ($info === false || ! in_array($info[2], self::TYPES, true)) {
            throw new InvalidPhoto('Photos must be JPEG, PNG or WebP images.');
        }

        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width * $height > config('community.photos.max_megapixels') * 1_000_000) {
            throw new InvalidPhoto('This photo is too large. Please choose a smaller one.');
        }

        $this->ensureMemory();

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG  => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
        };
        if (! $image instanceof GdImage) {
            throw new InvalidPhoto('This photo could not be read. Please try another one.');
        }

        try {
            [$full, $fullWidth, $fullHeight] = $this->jpeg($image, $width, $height, (int) config('community.photos.long_side'));
            [$thumb] = $this->jpeg($image, $width, $height, (int) config('community.photos.thumb_side'));
        } finally {
            imagedestroy($image);
        }

        $base = now()->format('Y/m') . '/' . Str::uuid()->toString();
        $disk = Storage::disk(config('community.photos.disk'));
        $disk->put("{$base}.jpg", $full);
        $disk->put("{$base}_thumb.jpg", $thumb);

        return [
            'path'       => "{$base}.jpg",
            'thumb_path' => "{$base}_thumb.jpg",
            'width'      => $fullWidth,
            'height'     => $fullHeight,
            'bytes'      => strlen($full),
        ];
    }

    /** Deletes a stored photo's files (both sizes). */
    public function delete(string $path, string $thumbPath): void
    {
        Storage::disk(config('community.photos.disk'))->delete([$path, $thumbPath]);
    }

    /** @return array{0: string, 1: int, 2: int} JPEG bytes no larger than $longSide on the long side (never upscaled) */
    private function jpeg(GdImage $image, int $width, int $height, int $longSide): array
    {
        $scale = min(1, $longSide / max($width, $height));
        $w = max(1, (int) round($width * $scale));
        $h = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($w, $h);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));   // transparency -> white
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $w, $h, $width, $height);

        ob_start();
        imagejpeg($canvas, null, (int) config('community.photos.quality'));
        $bytes = (string) ob_get_clean();
        imagedestroy($canvas);

        return [$bytes, $w, $h];
    }

    /** A 40 MP photo needs about 160 MB decoded; raise a lower memory_limit for this request. */
    private function ensureMemory(): void
    {
        $limit = ini_get('memory_limit');
        if ($limit === false || $limit === '-1') {
            return;
        }

        $bytes = (int) $limit;
        $unit = strtolower(substr(trim($limit), -1));
        $bytes *= match ($unit) { 'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1 };

        if ($bytes < 384 * 1024 ** 2) {
            ini_set('memory_limit', '384M');
        }
    }
}
