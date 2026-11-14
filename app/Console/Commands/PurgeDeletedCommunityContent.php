<?php

namespace App\Console\Commands;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityReport;
use App\Support\Community\PhotoProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes for good the community posts and comments that were deleted more than
 * community.purge_after_days ago (kept until then as evidence), with their photos
 * (files and rows), likes, comments and reports. Hidden but not deleted items are kept.
 * Scheduled daily (routes/console.php).
 */
class PurgeDeletedCommunityContent extends Command
{
    protected $signature = 'community:purge-deleted {--days= : Override community.purge_after_days}';

    protected $description = 'Permanently remove community posts, comments and photos deleted more than 30 days ago';

    public function handle(PhotoProcessor $photos): int
    {
        $days = (int) ($this->option('days') ?? config('community.purge_after_days'));
        $cutoff = now()->subDays($days);

        $posts = 0;
        CommunityPost::onlyTrashed()->where('deleted_at', '<', $cutoff)->with('photos')
            ->chunkById(100, function ($chunk) use ($photos, &$posts) {
                foreach ($chunk as $post) {
                    DB::transaction(function () use ($post) {
                        $commentIds = CommunityComment::withTrashed()->where('post_id', $post->id)->pluck('id');
                        CommunityReport::where('target_type', CommunityReport::TARGET_COMMENT)->whereIn('target_id', $commentIds)->delete();
                        CommunityReport::where('target_type', CommunityReport::TARGET_POST)->where('target_id', $post->id)->delete();
                        $post->forceDelete();   // photos, likes and comments go with it (foreign keys cascade)
                    });
                    foreach ($post->photos as $photo) {
                        $photos->delete($photo->path, $photo->thumb_path);
                    }
                    $posts++;
                }
            });

        $comments = 0;
        CommunityComment::onlyTrashed()->where('deleted_at', '<', $cutoff)
            ->chunkById(500, function ($chunk) use (&$comments) {
                $ids = $chunk->pluck('id');
                DB::transaction(function () use ($ids) {
                    CommunityReport::where('target_type', CommunityReport::TARGET_COMMENT)->whereIn('target_id', $ids)->delete();
                    CommunityComment::onlyTrashed()->whereIn('id', $ids)->forceDelete();
                });
                $comments += $ids->count();
            });

        $this->info("Purged {$posts} post(s) and {$comments} comment(s) deleted before {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
