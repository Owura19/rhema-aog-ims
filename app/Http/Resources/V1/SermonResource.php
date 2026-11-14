<?php

namespace App\Http\Resources\V1;

use App\Models\Sermon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published sermon as the app shows it. Exactly these keys: never status,
 * authorship, the preacher's member link, verse columns or timestamps other
 * than published_at. Expects series to be loaded.
 *
 * @mixin Sermon
 */
class SermonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'title'               => $this->title,
            'preacher'            => $this->preacher_name,
            'preached_on'         => $this->preached_on->toDateString(),
            'scripture_reference' => $this->scripture_reference,
            'series'              => $this->series ? ['id' => $this->series->id, 'title' => $this->series->title] : null,
            'summary'             => $this->summary,
            'youtube'             => $this->youtube_video_id
                ? ['video_id' => $this->youtube_video_id, 'url' => $this->youtubeUrl()]
                : null,
            'audio_url'           => $this->audio_url,
            'thumbnail_url'       => $this->thumbnailUrl(),
            'published_at'        => $this->published_at?->toIso8601String(),
        ];
    }
}
