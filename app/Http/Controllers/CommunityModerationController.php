<?php

namespace App\Http\Controllers;

use App\Models\CommunityBan;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityPostPhoto;
use App\Models\CommunityReport;
use App\Models\Member;
use App\Support\Community\Moderation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The community moderation queue ('moderate community': Super Admin and Pastor).
 *  - Queue: items with open reports, automatically hidden ones first, then by report count.
 *  - An item: its text, photos, reports; hide (with a reason), unhide, dismiss reports.
 *  - A member: their community posts, comments and bans; ban until a date or until lifted.
 * {type} is "post" or "comment" throughout.
 */
class CommunityModerationController extends Controller
{
    public function index()
    {
        $groups = CommunityReport::open()->orderBy('created_at')->get()
            ->groupBy(fn (CommunityReport $r) => "{$r->target_type}:{$r->target_id}");

        $items = $groups->map(function (Collection $reports) {
            $first = $reports->first();
            $item = $this->find($first->target_type, $first->target_id);

            return [
                'type'    => $first->target_type,
                'id'      => $first->target_id,
                'item'    => $item,
                'count'   => $reports->count(),
                'reasons' => $reports->countBy(fn (CommunityReport $r) => $r->reasonLabel())->sortDesc(),
                'latest'  => $reports->max('created_at'),
            ];
        })
            ->filter(fn (array $row) => $row['item'] !== null)
            ->sortBy([
                fn (array $a, array $b) => ($b['item']->hidden_automatically && $b['item']->isHidden()) <=> ($a['item']->hidden_automatically && $a['item']->isHidden()),
                fn (array $a, array $b) => $b['count'] <=> $a['count'],
            ])
            ->values();

        $bans = CommunityBan::active()->with('member')->latest()->get();

        return view('community-moderation.index', compact('items', 'bans'));
    }

    public function show(string $type, int $id)
    {
        $item = $this->find($type, $id);
        abort_unless($item, 404);

        $item->load($type === CommunityReport::TARGET_POST ? ['author', 'photos', 'hiddenBy'] : ['author', 'post.author', 'hiddenBy']);
        $comments = $type === CommunityReport::TARGET_POST
            ? CommunityComment::withTrashed()->with('author')->where('post_id', $item->id)->orderBy('id')->get()
            : collect();
        $reports = CommunityReport::for($type, $id)->with(['reporter', 'reviewedBy'])->latest()->get();

        return view('community-moderation.show', compact('type', 'item', 'comments', 'reports'));
    }

    public function hide(Request $request, string $type, int $id): RedirectResponse
    {
        $item = $this->find($type, $id);
        abort_unless($item, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        Moderation::hide($item, $request->user(), $data['reason']);

        return back()->with('success', ucfirst($type) . ' hidden. Its open reports were marked as actioned.');
    }

    public function unhide(Request $request, string $type, int $id): RedirectResponse
    {
        $item = $this->find($type, $id);
        abort_unless($item, 404);

        Moderation::unhide($item, $request->user());

        return back()->with('success', ucfirst($type) . ' is visible again. Its open reports were dismissed.');
    }

    public function dismiss(Request $request, string $type, int $id): RedirectResponse
    {
        $item = $this->find($type, $id);
        abort_unless($item, 404);

        $closed = Moderation::closeReports($item, CommunityReport::STATUS_DISMISSED, $request->user());

        return back()->with('success', "{$closed} report(s) dismissed.");
    }

    public function member(Member $member)
    {
        $posts = CommunityPost::withTrashed()->where('member_id', $member->id)->latest('id')->limit(50)->get();
        $comments = CommunityComment::withTrashed()->where('member_id', $member->id)->latest('id')->limit(50)->get();
        $bans = CommunityBan::with(['bannedBy', 'liftedBy'])->where('member_id', $member->id)->latest()->get();
        $activeBan = CommunityBan::activeFor($member->id);

        return view('community-moderation.member', compact('member', 'posts', 'comments', 'bans', 'activeBan'));
    }

    public function ban(Request $request, Member $member): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'until'  => ['nullable', 'date_format:Y-m-d', 'after:today'],
        ]);

        CommunityBan::create([
            'member_id'    => $member->id,
            'reason'       => $data['reason'],
            'banned_by'    => $request->user()->id,
            'banned_until' => isset($data['until']) ? $data['until'] . ' 23:59:59' : null,
        ]);

        return back()->with('success', isset($data['until'])
            ? "{$member->full_name} can't post, comment or like until " . \Illuminate\Support\Carbon::parse($data['until'])->format('j M Y') . '.'
            : "{$member->full_name} can't post, comment or like until the ban is lifted.");
    }

    public function liftBan(Request $request, CommunityBan $ban): RedirectResponse
    {
        if ($ban->lifted_at === null) {
            $ban->update(['lifted_at' => now(), 'lifted_by' => $request->user()->id]);
        }

        return back()->with('success', 'Ban lifted.');
    }

    /** A post's photo for moderators (any state, including hidden or deleted posts). */
    public function photo(CommunityPostPhoto $photo, string $variant): Response
    {
        $disk = Storage::disk(config('community.photos.disk'));
        $path = $photo->pathFor($variant);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store']);
    }

    private function find(string $type, int $id): CommunityPost|CommunityComment|null
    {
        return match ($type) {
            CommunityReport::TARGET_POST    => CommunityPost::withTrashed()->find($id),
            CommunityReport::TARGET_COMMENT => CommunityComment::withTrashed()->find($id),
            default                         => null,
        };
    }
}
