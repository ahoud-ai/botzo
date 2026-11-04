<?php

namespace App\Exceptions;

use RuntimeException;

/** The Salla plans do not exist yet: run `php artisan salla:setup-plans`. */
class SallaPlansMissing extends RuntimeException
{
}
