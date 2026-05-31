<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Enums;

/**
 * Lifecycle states of a received webhook as it moves through the queue.
 */
enum WebhookStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
}
