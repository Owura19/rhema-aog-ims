<?php

namespace App\Services\Harvest;

use App\Models\Harvest;
use App\Models\Transaction;

/** What the system suggests for one unlinked Harvest transaction. Finance confirms or changes it. */
final class HarvestSuggestion
{
    /**
     * @param  string  $basis  'description' (year written in it), 'date' (fell back to the date) or 'none'
     * @param  array<int, list<array<string, mixed>>>  $optionsByHarvest  pledge options per harvest id
     * @param  array<int, ?string>  $choiceByHarvest  suggested option value per harvest id: a pledge id, 'giver' or null
     * @param  array<int, list<string>>  $pledgeFlagsByHarvest  pledge flags per harvest id
     * @param  list<string>  $campaignFlags
     */
    public function __construct(
        public readonly Transaction $transaction,
        public readonly ?Harvest $harvest,
        public readonly string $basis,
        public readonly ?int $year,
        public readonly array $optionsByHarvest,
        public readonly array $choiceByHarvest,
        public readonly array $pledgeFlagsByHarvest,
        public readonly array $campaignFlags,
    ) {}

    /** @return list<array<string, mixed>> pledge options on the suggested harvest */
    public function options(): array
    {
        return $this->harvest ? ($this->optionsByHarvest[$this->harvest->id] ?? []) : [];
    }

    public function choice(): ?string
    {
        return $this->harvest ? ($this->choiceByHarvest[$this->harvest->id] ?? null) : null;
    }

    /** @return list<string> */
    public function flags(): array
    {
        $pledgeFlags = $this->harvest ? ($this->pledgeFlagsByHarvest[$this->harvest->id] ?? []) : [];

        return array_values(array_unique([...$this->campaignFlags, ...$pledgeFlags]));
    }

    /** A clear case: campaign and pledge suggested, nothing to check. */
    public function isClean(): bool
    {
        return $this->harvest !== null && $this->choice() !== null && $this->flags() === [];
    }
}
