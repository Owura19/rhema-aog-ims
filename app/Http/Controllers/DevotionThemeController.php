<?php

namespace App\Http\Controllers;

use App\Models\Devotion;
use App\Models\DevotionTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Themes the pastor sets for a run of dates. Themes may overlap: for any date, the
 * one that started most recently applies (DevotionTheme::covering()).
 */
class DevotionThemeController extends Controller
{
    public function index()
    {
        return view('devotions.themes.index', [
            'themes'  => DevotionTheme::withCount('devotions')->orderByDesc('starts_on')->orderByDesc('id')->get(),
            'current' => DevotionTheme::covering(today()),
        ]);
    }

    public function create()
    {
        return view('devotions.themes.create', ['theme' => new DevotionTheme()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $theme = DevotionTheme::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('devotion-themes.index')->with('success', "Theme \"{$theme->title}\" added.");
    }

    public function edit(DevotionTheme $theme)
    {
        return view('devotions.themes.edit', compact('theme'));
    }

    public function update(Request $request, DevotionTheme $theme): RedirectResponse
    {
        $theme->update($this->validated($request));

        return redirect()->route('devotion-themes.index')->with('success', "Theme \"{$theme->title}\" updated.");
    }

    public function destroy(DevotionTheme $theme): RedirectResponse
    {
        DB::transaction(function () use ($theme) {
            // The foreign key clears theme_id; also flag those devotions as having no theme.
            Devotion::withTrashed()->where('theme_id', $theme->id)->update(['no_theme' => true]);
            $theme->delete();
        });

        return redirect()->route('devotion-themes.index')->with('success', "Theme \"{$theme->title}\" deleted.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on'   => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'notes'     => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
