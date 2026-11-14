<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The members' community feed (mobile app). New community_* tables keyed to members;
 * the old staff "Community" tables (posts, post_likes, post_comments) are left untouched.
 * Photos live on the private 'community' disk, never the public one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->text('body')->nullable();                          // at most 2,000 characters (validated)
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->unsignedInteger('report_count')->default(0);
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hide_reason', 255)->nullable();
            $table->boolean('hidden_automatically')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['deleted_at', 'hidden_at', 'id']);
        });

        Schema::create('community_post_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->string('path');
            $table->string('thumb_path');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedInteger('bytes');
            $table->unsignedTinyInteger('position');                   // 0-3
            $table->timestamps();
        });

        Schema::create('community_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['post_id', 'member_id']);
        });

        Schema::create('community_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->text('body');                                      // at most 1,000 characters (validated)
            $table->unsignedInteger('report_count')->default(0);
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hide_reason', 255)->nullable();
            $table->boolean('hidden_automatically')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['post_id', 'id']);
        });

        Schema::create('community_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_member_id')->constrained('members')->cascadeOnDelete();
            $table->enum('target_type', ['post', 'comment']);
            $table->unsignedBigInteger('target_id');
            $table->enum('reason', ['spam', 'harassment', 'hate', 'sexual', 'violence', 'privacy', 'false_information', 'other']);
            $table->string('details', 500)->nullable();
            $table->enum('status', ['open', 'actioned', 'dismissed'])->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['reporter_member_id', 'target_type', 'target_id'], 'community_reports_once_per_reporter');
            $table->index(['target_type', 'target_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('community_member_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('blocked_member_id')->constrained('members')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['blocker_member_id', 'blocked_member_id'], 'community_blocks_pair_unique');
            $table->index('blocked_member_id');
        });

        Schema::create('community_bans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('reason', 500);
            $table->foreignId('banned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('banned_until')->nullable();             // null = until lifted
            $table->timestamp('lifted_at')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('community_rule_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('rules_version', 20);
            $table->boolean('adult_confirmed')->default(false);        // "I am 18 or older" (needed when the date of birth is unknown)
            $table->timestamp('accepted_at');

            $table->unique(['member_id', 'rules_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_rule_acceptances');
        Schema::dropIfExists('community_bans');
        Schema::dropIfExists('community_member_blocks');
        Schema::dropIfExists('community_reports');
        Schema::dropIfExists('community_comments');
        Schema::dropIfExists('community_likes');
        Schema::dropIfExists('community_post_photos');
        Schema::dropIfExists('community_posts');
    }
};
