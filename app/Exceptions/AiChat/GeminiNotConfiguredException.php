<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use RuntimeException;

final class GeminiNotConfiguredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Gemini API key is not configured.');
    }
}
