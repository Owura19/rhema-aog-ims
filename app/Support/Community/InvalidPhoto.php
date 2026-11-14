<?php

namespace App\Support\Community;

use RuntimeException;

/** A photo the feed can't accept; the message is safe to show the member. */
final class InvalidPhoto extends RuntimeException
{
}
