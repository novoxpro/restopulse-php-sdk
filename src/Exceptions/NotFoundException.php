<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Exceptions;

/**
 * Ресурс не найден.
 *
 * Возникает, когда запрашиваемый ресурс отсутствует на стороне API.
 */
final class NotFoundException extends RestopulseException
{
}
