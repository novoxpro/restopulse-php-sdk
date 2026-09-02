<?php

declare(strict_types=1);

namespace Restopulse\PhpSdk\Exceptions;

/**
 * Ошибка сетевого взаимодействия.
 *
 * Возникает при недоступности сервера, ошибке DNS, таймауте соединения
 * и других ошибках HTTP-клиента.
 */
final class NetworkException extends RestopulseException
{
}
