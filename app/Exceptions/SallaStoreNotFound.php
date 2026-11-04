<?php

namespace App\Exceptions;

use RuntimeException;

/** A subscription event came before the store's install event. Salla retries it, by which time the store exists. */
class SallaStoreNotFound extends RuntimeException
{
}
