<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Exceptions;

/**
 * Ошибка авторизации.
 *
 * Возникает при использовании недействительного или отсутствующего API Key.
 */
final class UnauthorizedException extends RestopulseException
{
}
