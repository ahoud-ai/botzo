<?php

namespace App\Exceptions;

use RuntimeException;

/** An app.store.authorize event had no tokens. Its class name goes in the event log; nobody outside sees it. */
class SallaAuthorizeWithoutTokens extends RuntimeException
{
}
