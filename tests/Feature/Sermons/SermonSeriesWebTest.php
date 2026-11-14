<?php

namespace Tests\Feature\Sermons;

use App\Models\Sermon;
use App\Models\SermonSeries;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The sermon series pages ('manage sermons'). */
class SermonSeriesWebTest extends TestCase
{
    use RefreshDatabase;

    private const ROLES = ['Super Admin', 'Pastor', 'Office Staff', 'HOD', 'Usher'];

    /** @var array<string, User> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (self::ROLES as $role) {
            $this->users[$role] = User::factory()->create(['name' => "{$role} User"])->assignRole($role);
        }
    }

    private function as(string $role): static
    {
        return $this->actingAs($this->users[$role]);
    }

    public static function routes(): array
    {
        return [
            'index'   => ['GET', 'sermon-series.index', false],
            'create'  => ['GET', 'sermon-series.create', false],
            'store'   => ['POST', 'sermon-series.store', false],
            'edit'    => ['GET', 'sermon-series.edit', true],
            'update'  => ['PUT', 'sermon-series.update', true],
            'destroy' => ['DELETE', 'sermon-series.destroy', true],
        ];
    }

    #[DataProvider('routes')]
    public function test_only_sermon_managers_can_use_the_series_pages(string $method, string $route, bool $needsSeries): void
    {
        foreach (self::ROLES as $role) {
            $series = $needsSeries ? SermonSeries::create(['title' => 'The Book of Acts']) : null;
            $data = in_array($method, ['POST', 'PUT'], true) ? ['title' => 'Kingdom Living'] : [];

            $response = $this->as($role)->call($method, $series ? route($route, $series) : route($route), $data);

            if (in_array($role, ['Super Admin', 'Pastor', 'Office Staff'], true)) {
                $this->assertContains($response->getStatusCode(), [200, 302], "{$role} should be allowed to use {$route}");
            } else {
                $this->assertSame(403, $response->getStatusCode(), "{$role} should be refused {$route}");
            }
        }
    }

    public function test_a_series_can_be_created_edited_and_listed(): void
    {
        $this->as('Pastor')->post(route('sermon-series.store'), ['title' => 'The Book of Acts', 'description' => 'Chapter by chapter.'])
            ->assertRedirect(route('sermon-series.index'))
            ->assertSessionHas('success', 'Series "The Book of Acts" added.');

        $series = SermonSeries::sole();
        $this->assertSame('Chapter by chapter.', $series->description);

        $this->as('Office Staff')->put(route('sermon-series.update', $series), ['title' => 'Acts of the Apostles', 'description' => ''])
            ->assertRedirect(route('sermon-series.index'));
        $series->refresh();
        $this->assertSame('Acts of the Apostles', $series->title);
        $this->assertNull($series->description);

        Sermon::create(['title' => 'Pentecost', 'preacher_name' => 'Rev. Mensah', 'preached_on' => '2026-11-08', 'series_id' => $series->id]);
        $this->as('Pastor')->get(route('sermon-series.index'))->assertOk()->assertSee('Acts of the Apostles');
        $this->as('Pastor')->get(route('sermon-series.edit', $series))->assertOk()->assertSee('Acts of the Apostles');
        $this->as('Pastor')->get(route('sermon-series.create'))->assertOk();
    }

    public function test_validation(): void
    {
        $this->as('Pastor')->post(route('sermon-series.store'), [])->assertSessionHasErrors('title');
        $this->as('Pastor')->post(route('sermon-series.store'), ['title' => str_repeat('a', 151)])->assertSessionHasErrors('title');
        $this->as('Pastor')->post(route('sermon-series.store'), ['title' => 'Ok', 'description' => str_repeat('a', 2001)])->assertSessionHasErrors('description');

        $this->assertSame(0, SermonSeries::count());
    }

    public function test_deleting_a_series_keeps_its_sermons(): void
    {
        $series = SermonSeries::create(['title' => 'The Book of Acts']);
        $sermon = Sermon::create(['title' => 'Pentecost', 'preacher_name' => 'Rev. Mensah', 'preached_on' => '2026-11-08', 'series_id' => $series->id]);

        $this->as('Pastor')->delete(route('sermon-series.destroy', $series))
            ->assertRedirect(route('sermon-series.index'))
            ->assertSessionHas('success', 'Series "The Book of Acts" deleted. Its sermons were kept.');

        $this->assertSame(0, SermonSeries::count());
        $this->assertNull($sermon->fresh()->series_id);
    }

    public function test_the_sermon_list_links_to_the_series_page(): void
    {
        $this->as('Office Staff')->get(route('sermons.index'))->assertOk()->assertSee(route('sermon-series.index'));
    }
}
