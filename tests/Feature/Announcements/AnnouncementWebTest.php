<?php

namespace Tests\Feature\Announcements;

use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The announcement pages ('manage announcements'). Now is 2026-11-16 10:00 UTC. */
class AnnouncementWebTest extends TestCase
{
    use RefreshDatabase;

    private const ROLES = ['Super Admin', 'Pastor', 'Office Staff', 'HOD', 'Usher'];
    private const ALLOWED = ['Super Admin', 'Pastor', 'Office Staff'];

    /** @var array<string, User> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (self::ROLES as $role) {
            $this->users[$role] = User::factory()->create(['name' => "{$role} User"])->assignRole($role);
        }
    }

    private function as(string $role): static
    {
        return $this->actingAs($this->users[$role]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + ['title' => 'Harvest Sunday', 'body' => 'Bring your harvest offering next Sunday.'];
    }

    private function announcement(array $attributes = []): Announcement
    {
        return Announcement::create($attributes + ['title' => 'Harvest Sunday', 'body' => 'Bring your harvest offering.']);
    }

    public static function routes(): array
    {
        return [
            'index'     => ['GET', 'announcements.index', false],
            'create'    => ['GET', 'announcements.create', false],
            'store'     => ['POST', 'announcements.store', false],
            'edit'      => ['GET', 'announcements.edit', true],
            'update'    => ['PUT', 'announcements.update', true],
            'destroy'   => ['DELETE', 'announcements.destroy', true],
            'publish'   => ['POST', 'announcements.publish', true],
            'unpublish' => ['POST', 'announcements.unpublish', true],
        ];
    }

    #[DataProvider('routes')]
    public function test_only_announcement_managers_can_use_the_pages(string $method, string $route, bool $needsItem): void
    {
        foreach (self::ROLES as $role) {
            $item = $needsItem ? $this->announcement() : null;
            $data = in_array($method, ['POST', 'PUT'], true) ? $this->payload() : [];

            $response = $this->as($role)->call($method, $item ? route($route, $item) : route($route), $data);

            if (in_array($role, self::ALLOWED, true)) {
                $this->assertContains($response->getStatusCode(), [200, 302], "{$role} should be allowed to use {$route}");
            } else {
                $this->assertSame(403, $response->getStatusCode(), "{$role} should be refused {$route}");
            }
        }
    }

    public function test_the_sidebar_link_is_only_for_managers(): void
    {
        $this->as('Office Staff')->get(route('dashboard'))->assertSee(route('announcements.index'));
        $this->as('HOD')->get(route('dashboard'))->assertDontSee(route('announcements.index'));
    }

    public function test_a_new_announcement_is_a_draft_starting_now(): void
    {
        $this->as('Pastor')->post(route('announcements.store'), $this->payload([
            'title'     => '  Harvest Sunday  ',
            'link_url'  => 'https://rhemaaog-ims.com/harvest',
            'is_pinned' => '1',
            'ends_at'   => '2026-11-23T12:00',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('announcements.index'));

        $announcement = Announcement::sole();
        $this->assertSame('Harvest Sunday', $announcement->title);
        $this->assertSame(Announcement::STATUS_DRAFT, $announcement->status);
        $this->assertTrue($announcement->is_pinned);
        $this->assertSame('2026-11-16 10:00:00', $announcement->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-23 12:00:00', $announcement->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame($this->users['Pastor']->id, $announcement->created_by);
    }

    public function test_validation(): void
    {
        $this->as('Pastor')->post(route('announcements.store'), [])->assertSessionHasErrors(['title', 'body']);
        $this->as('Pastor')->post(route('announcements.store'), $this->payload(['title' => str_repeat('a', 121)]))->assertSessionHasErrors('title');
        $this->as('Pastor')->post(route('announcements.store'), $this->payload(['body' => str_repeat('a', 1001)]))->assertSessionHasErrors('body');
        $this->as('Pastor')->post(route('announcements.store'), $this->payload(['link_url' => 'http://example.org']))
            ->assertSessionHasErrors(['link_url' => 'The link must be a full https:// address.']);
        $this->as('Pastor')->post(route('announcements.store'), $this->payload(['link_url' => 'not a link']))->assertSessionHasErrors('link_url');
        $this->as('Pastor')->post(route('announcements.store'), $this->payload(['starts_at' => '2026-11-20T09:00', 'ends_at' => '2026-11-20T09:00']))
            ->assertSessionHasErrors(['ends_at' => 'The end must be after the start.']);
        $this->as('Pastor')->post(route('announcements.store'), $this->payload(['ends_at' => '2026-11-16T09:00']))   // before "now"
            ->assertSessionHasErrors('ends_at');

        $this->assertSame(0, Announcement::count());

        $this->as('Pastor')->post(route('announcements.store'), $this->payload(['title' => str_repeat('a', 120), 'body' => str_repeat('b', 1000)]))
            ->assertSessionHasNoErrors();
    }

    public function test_editing_records_the_editor(): void
    {
        $announcement = $this->announcement();

        $this->as('Office Staff')->put(route('announcements.update', $announcement), $this->payload(['title' => 'Changed', 'starts_at' => '2026-11-18T08:00']))
            ->assertRedirect(route('announcements.index'));

        $announcement->refresh();
        $this->assertSame('Changed', $announcement->title);
        $this->assertSame('2026-11-18 08:00:00', $announcement->starts_at->format('Y-m-d H:i:s'));
        $this->assertFalse($announcement->is_pinned);
        $this->assertSame($this->users['Office Staff']->id, $announcement->updated_by);
    }

    public function test_publish_and_unpublish(): void
    {
        $announcement = $this->announcement(['starts_at' => now()->subHour()]);

        $this->as('Pastor')->post(route('announcements.publish', $announcement))->assertSessionHas('success', 'Announcement published. It now shows in the app.');
        $this->assertSame([$announcement->id], Announcement::live()->pluck('id')->all());
        $this->as('Pastor')->post(route('announcements.publish', $announcement))->assertSessionHas('error');

        $this->as('Pastor')->post(route('announcements.unpublish', $announcement))->assertSessionHas('success');
        $this->assertSame(Announcement::STATUS_DRAFT, $announcement->fresh()->status);
        $this->assertSame(0, Announcement::live()->count());
        $this->as('Pastor')->post(route('announcements.unpublish', $announcement))->assertSessionHas('error');

        $later = $this->announcement(['starts_at' => '2026-11-20 09:00:00']);
        $this->as('Pastor')->post(route('announcements.publish', $later))
            ->assertSessionHas('success', 'Announcement published. It will show in the app from 20 Nov 2026, 9:00 AM.');
    }

    public function test_deleting_is_a_soft_delete(): void
    {
        $announcement = $this->announcement(['status' => 'published', 'starts_at' => now()->subHour()]);

        $this->as('Pastor')->delete(route('announcements.destroy', $announcement))->assertRedirect(route('announcements.index'));

        $this->assertTrue(Announcement::withTrashed()->find($announcement->id)->trashed());
        $this->assertSame(0, Announcement::live()->count());
    }

    public function test_the_list_shows_states_and_filters(): void
    {
        $this->announcement(['title' => 'Live one', 'status' => 'published', 'starts_at' => now()->subDay()]);
        $this->announcement(['title' => 'Later one', 'status' => 'published', 'starts_at' => now()->addDay()]);
        $this->announcement(['title' => 'Old one', 'status' => 'published', 'starts_at' => now()->subWeek(), 'ends_at' => now()->subDay()]);
        $this->announcement(['title' => 'Draft one']);

        $this->as('Pastor')->get(route('announcements.index'))->assertOk()
            ->assertSee('Live one')->assertSee('Showing in app')
            ->assertSee('Later one')->assertSee('Scheduled')
            ->assertSee('Old one')->assertSee('Expired')
            ->assertSee('Draft one');

        foreach (['active' => 'Live one', 'scheduled' => 'Later one', 'expired' => 'Old one', 'draft' => 'Draft one'] as $state => $title) {
            $page = $this->as('Pastor')->get(route('announcements.index', ['state' => $state]))->assertOk()->assertSee($title);
            foreach (['Live one', 'Later one', 'Old one', 'Draft one'] as $other) {
                if ($other !== $title) {
                    $page->assertDontSee($other);
                }
            }
        }

        $this->as('Pastor')->get(route('announcements.create'))->assertOk();
        $this->as('Pastor')->get(route('announcements.edit', Announcement::first()))->assertOk();
    }
}
