<?php

namespace App\Domain\Access\Exceptions;

use RuntimeException;

/** A user/role change that would break an access invariant (e.g. no active admin left). */
final class AccessRuleViolation extends RuntimeException {}
