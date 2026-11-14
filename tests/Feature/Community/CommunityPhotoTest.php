<?php

namespace Tests\Feature\Community;

use App\Models\CommunityMemberBlock;
use App\Models\CommunityPost;
use App\Models\CommunityPostPhoto;
use App\Models\Member;
use App\Support\Community\InvalidPhoto;
use App\Support\Community\PhotoProcessor;
use App\Support\Community\PhotoUrls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** PhotoProcessor (GD: resize, JPEG, EXIF removed) and the signed photo route. */
class CommunityPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));
        Storage::fake('community');
    }

    /** A real JPEG (GD) with an APP1 Exif segment holding a GPS marker spliced in after SOI. */
    public static function jpegWithExif(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 90, 160));
        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = (string) ob_get_clean();

        $payload = "Exif\0\0" . 'MM' . "\0\x2A\0\0\0\x08" . 'GPSLatitude=5.6037N;GPSLongitude=0.1870W;SECRET-LOCATION';
        $app1 = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;

        return substr($jpeg, 0, 2) . $app1 . substr($jpeg, 2);
    }

    private function upload(string $bytes, string $name = 'photo.jpg'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cph');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function member(string $first = 'Ama'): Member
    {
        return Member::create(['first_name' => $first, 'last_name' => 'Mensah', 'gender' => 'Female']);
    }

    private function photoOf(CommunityPost $post): CommunityPostPhoto
    {
        $stored = app(PhotoProcessor::class)->store(UploadedFile::fake()->image('p.jpg', 800, 600));

        return CommunityPostPhoto::create($stored + ['post_id' => $post->id, 'position' => 0]);
    }

    // ── processing ─────────────────────────────────────────────────────

    public function test_a_large_photo_is_resized_to_a_jpeg_with_a_thumbnail(): void
    {
        $stored = app(PhotoProcessor::class)->store(UploadedFile::fake()->image('wide.png', 3200, 2000));

        $this->assertSame(1600, $stored['width']);
        $this->assertSame(1000, $stored['height']);
        $this->assertMatchesRegularExpression('#^2026/11/[0-9a-f-]{36}\.jpg$#', $stored['path']);
        $this->assertSame(str_replace('.jpg', '_thumb.jpg', $stored['path']), $stored['thumb_path']);

        $disk = Storage::disk('community');
        $full = getimagesizefromstring($disk->get($stored['path']));
        $thumb = getimagesizefromstring($disk->get($stored['thumb_path']));
        $this->assertSame([1600, 1000, IMAGETYPE_JPEG], [$full[0], $full[1], $full[2]]);
        $this->assertSame([640, 400, IMAGETYPE_JPEG], [$thumb[0], $thumb[1], $thumb[2]]);
        $this->assertSame(strlen($disk->get($stored['path'])), $stored['bytes']);
    }

    public function test_a_small_photo_is_not_upscaled(): void
    {
        $stored = app(PhotoProcessor::class)->store(UploadedFile::fake()->image('small.jpg', 300, 200));

        $this->assertSame([300, 200], [$stored['width'], $stored['height']]);
    }

    public function test_exif_and_gps_are_removed(): void
    {
        $original = self::jpegWithExif(1200, 900);
        $this->assertStringContainsString('SECRET-LOCATION', $original);   // the fixture really carries it
        $this->assertNotFalse(getimagesizefromstring($original));

        $stored = app(PhotoProcessor::class)->store($this->upload($original));

        foreach ([$stored['path'], $stored['thumb_path']] as $path) {
            $bytes = Storage::disk('community')->get($path);
            $this->assertStringNotContainsString('SECRET-LOCATION', $bytes);
            $this->assertStringNotContainsString('GPSLatitude', $bytes);
            $this->assertStringNotContainsString("Exif\0\0", $bytes);
        }
    }

    public function test_non_images_and_other_types_are_refused(): void
    {
        $processor = app(PhotoProcessor::class);

        try {
            $processor->store($this->upload('not an image at all', 'x.jpg'));
            $this->fail('Text accepted as a photo');
        } catch (InvalidPhoto $e) {
            $this->assertSame('Photos must be JPEG, PNG or WebP images.', $e->getMessage());
        }

        $this->expectException(InvalidPhoto::class);
        $processor->store(UploadedFile::fake()->image('anim.gif', 50, 50));
    }

    public function test_photos_over_the_megapixel_limit_are_refused_before_decoding(): void
    {
        config(['community.photos.max_megapixels' => 1]);   // 1,000,000 pixels

        $this->expectExceptionObject(new InvalidPhoto('This photo is too large. Please choose a smaller one.'));
        app(PhotoProcessor::class)->store(UploadedFile::fake()->image('big.jpg', 1001, 1000));
    }

    // ── signed serving ─────────────────────────────────────────────────

    public function test_a_signed_url_serves_the_photo_privately(): void
    {
        $author = $this->member();
        $photo = $this->photoOf(CommunityPost::create(['member_id' => $author->id]));
        $viewer = $this->member('Kofi');

        $response = $this->get(PhotoUrls::for($photo, 'full', $viewer->id))->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));

        $this->get(PhotoUrls::for($photo, 'thumb', $viewer->id))->assertOk();
    }

    public function test_unsigned_tampered_and_expired_urls_are_refused(): void
    {
        $author = $this->member();
        $photo = $this->photoOf(CommunityPost::create(['member_id' => $author->id]));
        $viewer = $this->member('Kofi');
        $url = PhotoUrls::for($photo, 'full', $viewer->id);

        $this->getJson("/api/v1/community/photos/{$photo->id}/full?viewer={$viewer->id}")->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->getJson(str_replace("viewer={$viewer->id}", 'viewer=999', $url))->assertForbidden();
        $this->getJson(str_replace('/full?', '/thumb?', $url))->assertForbidden();

        $this->travel(61)->minutes();
        $this->getJson($url)->assertForbidden();
    }

    public function test_photos_of_deleted_hidden_or_blocked_posts_are_not_served(): void
    {
        $author = $this->member();
        $viewer = $this->member('Kofi');

        $deleted = CommunityPost::create(['member_id' => $author->id]);
        $deletedPhoto = $this->photoOf($deleted);
        $deleted->delete();
        $this->getJson(PhotoUrls::for($deletedPhoto, 'full', $viewer->id))->assertNotFound();

        $hidden = CommunityPost::create(['member_id' => $author->id, 'hidden_at' => now()]);
        $hiddenPhoto = $this->photoOf($hidden);
        $this->getJson(PhotoUrls::for($hiddenPhoto, 'full', $viewer->id))->assertNotFound();
        $this->get(PhotoUrls::for($hiddenPhoto, 'full', $author->id))->assertOk();   // the author still sees it

        $visible = $this->photoOf(CommunityPost::create(['member_id' => $author->id]));
        CommunityMemberBlock::create(['blocker_member_id' => $author->id, 'blocked_member_id' => $viewer->id]);
        $this->getJson(PhotoUrls::for($visible, 'full', $viewer->id))->assertNotFound();   // blocked by the author
    }
}
