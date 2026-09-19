<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use RuntimeException;
use Throwable;

final class GeminiRequestException extends RuntimeException
{
    public function __construct(
        string $message = 'Gemini API request failed.',
        public readonly ?int $upstreamStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
