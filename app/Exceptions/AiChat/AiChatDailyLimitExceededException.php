<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use RuntimeException;

final class AiChatDailyLimitExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('本日の利用上限に達しました。明日 0:00 以降に再度ご利用ください。');
    }
}
