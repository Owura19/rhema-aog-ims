<?php

namespace App\Services\Harvest;

/** A harvest link that can't be made (already linked, not the payer's pledge, ...). The message is safe to show. */
class HarvestLinkException extends \DomainException
{
}
