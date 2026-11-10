<?php

namespace App\Support\Bible;

use InvalidArgumentException;

/** A scripture reference that cannot be used. The message is written for staff and shown as is. */
final class InvalidReference extends InvalidArgumentException
{
}
