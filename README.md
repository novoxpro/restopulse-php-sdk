# RestoPulse PHP SDK

Официальная PHP-библиотека для работы с Public API платформы RestoPulse.

## Установка

```bash
composer require novoxpro/restopulse-php-sdk
```

## Быстрый старт

```php
use Restopulse\PhpSdk\Client\Client;
use Restopulse\PhpSdk\Configuration\Configuration;
use Restopulse\PhpSdk\DTO\Request\EventDto;
use Restopulse\PhpSdk\DTO\Request\EventFieldDto;

$config = new Configuration(apiKey: 'ваш-api-key');
$client = new Client($config);

// Получение филиалов
$branches = $client->branches()->all();

// Отправка события
$event = new EventDto(
    branchIds: [47],
    eventType: 'order.created',
    externalId: 'ORD-100500',
    eventDate: new DateTimeImmutable('now'),
    title: 'Новый заказ',
    preview: 'Заказ оформлен на сайте',
    message: 'Создан заказ №100500.',
    fields: [new EventFieldDto('Номер заказа', '100500')],
);

$eventId = $client->events()->send($event)->getId();
```

## Конфигурация

```php
$config = new Configuration(
    apiKey: 'ваш-api-key',
    baseUrl: 'https://restopulse.ru',
    requestTimeout: 10.0,
    connectTimeout: 5.0,
    logger: $logger,
    maxRetries: 2,
    retryDelayMs: 1000,
    maxExecutionTime: 30.0,
);
```

## Документация

Подробная документация: https://docs.novox.ru/share/ixjas2zkvu/p/dokumentatsiya-resto-pulse-php-sdk-OQnGdfha3r

## Лицензия

MIT
