<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\AiChat\AiChatDailyLimitExceededException;
use App\Exceptions\AiChat\AiChatResponseException;
use App\Http\Requests\AiChat\StoreMessageRequest;
use App\Models\AiChatConversation;
use App\UseCases\AiChat\SendMessageAction;
use Illuminate\Http\JsonResponse;

class AiChatMessageController extends Controller
{
    public function store(
        AiChatConversation $conversation,
        StoreMessageRequest $request,
        SendMessageAction $action,
    ): JsonResponse {
        try {
            $messages = $action($request->user(), $conversation, $request->validated('content'));
        } catch (AiChatDailyLimitExceededException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error_code' => 'AI_CHAT_DAILY_LIMIT_EXCEEDED',
            ], 429);
        } catch (AiChatResponseException $exception) {
            return response()->json([
                'message' => 'AI が応答できませんでした。',
                'upstream_status' => $exception->upstreamStatus,
            ], 502);
        }

        return response()->json([
            ...$messages,
            'conversation' => $conversation->fresh(),
        ]);
    }
}
