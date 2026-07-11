<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by a model `deleting` guard when a record can't be deleted because
 * dependent records still reference it (a location with warehouses, or a
 * warehouse holding stock). Rendered as a redirect-back with an error toast
 * (see bootstrap/app.php).
 */
class BlockedByDependentsException extends RuntimeException {}
