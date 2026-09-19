<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use RuntimeException;
use Throwable;

final class AiChatResponseException extends RuntimeException
{
    public function __construct(
        public readonly ?int $upstreamStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct('AI が応答できませんでした。', 0, $previous);
    }
}
