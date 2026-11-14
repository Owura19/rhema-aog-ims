<?php

namespace App\Http\Controllers;

use App\Models\SermonSeries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Sermon series ('manage sermons'). Deleting a series keeps its sermons (series cleared). */
class SermonSeriesController extends Controller
{
    public function index()
    {
        return view('sermons.series.index', [
            'series' => SermonSeries::withCount('sermons')->orderBy('title')->get(),
        ]);
    }

    public function create()
    {
        return view('sermons.series.create', ['seriesItem' => new SermonSeries()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $series = SermonSeries::create($this->validated($request));

        return redirect()->route('sermon-series.index')->with('success', "Series \"{$series->title}\" added.");
    }

    public function edit(SermonSeries $series)
    {
        return view('sermons.series.edit', ['seriesItem' => $series]);
    }

    public function update(Request $request, SermonSeries $series): RedirectResponse
    {
        $series->update($this->validated($request));

        return redirect()->route('sermon-series.index')->with('success', "Series \"{$series->title}\" updated.");
    }

    public function destroy(SermonSeries $series): RedirectResponse
    {
        $series->delete();   // the foreign key clears series_id on its sermons

        return redirect()->route('sermon-series.index')->with('success', "Series \"{$series->title}\" deleted. Its sermons were kept.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'       => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
