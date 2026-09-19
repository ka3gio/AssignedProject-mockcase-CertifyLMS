<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AiChatMessageStatus;
use App\Exceptions\AiChat\AiChatDailyLimitExceededException;
use App\Exceptions\AiChat\AiChatResponseException;
use App\Http\Requests\AiChat\StoreConversationRequest;
use App\Http\Requests\AiChat\UpdateConversationRequest;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Section;
use App\UseCases\AiChat\CreateConversationAction;
use App\UseCases\AiChat\DestroyAction;
use App\UseCases\AiChat\IndexAction;
use App\UseCases\AiChat\SendMessageAction;
use App\UseCases\AiChat\ShowAction;
use App\UseCases\AiChat\UpdateAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AiChatConversationController extends Controller
{
    public function index(Request $request, IndexAction $action): View|RedirectResponse
    {
        $latest = $action($request->user());

        if ($latest !== null) {
            return redirect()->route('ai-chat.conversations.show', $latest);
        }

        return view('ai-chat.empty-state');
    }

    public function store(
        StoreConversationRequest $request,
        CreateConversationAction $createConversation,
        SendMessageAction $sendMessage,
    ): JsonResponse|RedirectResponse {
        $validated = $request->validated();
        $section = null;

        if (isset($validated['section_id'])) {
            $section = Section::query()->published()->findOrFail($validated['section_id']);
            Gate::authorize('learning.section.view', $section);
        }

        $conversation = $createConversation(
            $request->user(),
            $section,
            (bool) ($validated['auto_title_enabled'] ?? true),
        );

        if ($validated['source'] === 'widget') {
            return response()->json(
                ['conversation' => $conversation->load(['enrollment.certification', 'section'])],
                $conversation->wasRecentlyCreated ? 201 : 200,
            );
        }

        if (isset($validated['message']) && $validated['message'] !== '') {
            try {
                $sendMessage($request->user(), $conversation, $validated['message']);
            } catch (AiChatDailyLimitExceededException $exception) {
                return redirect()
                    ->route('ai-chat.conversations.show', $conversation)
                    ->with('error', $exception->getMessage());
            } catch (AiChatResponseException) {
                return redirect()
                    ->route('ai-chat.conversations.show', $conversation)
                    ->with('error', 'AI が応答できませんでした。時間をおいて再度お試しください。');
            }
        }

        return redirect()->route('ai-chat.conversations.show', $conversation);
    }

    public function show(
        AiChatConversation $conversation,
        Request $request,
        ShowAction $action,
    ): View|JsonResponse {
        $this->authorize('view', $conversation);

        $conversation = $action($conversation);

        if ($request->expectsJson()) {
            $messages = $conversation->messages->map(function (AiChatMessage $message): array {
                $data = $message->toArray();
                if ($message->status === AiChatMessageStatus::Error && blank($message->content)) {
                    $data['content'] = 'AI が応答できませんでした。しばらく時間をおいて再試行してください。';
                }

                return $data;
            });

            return response()->json([
                'conversation' => $conversation,
                'messages' => $messages,
            ]);
        }

        return view('ai-chat.show', ['conversation' => $conversation]);
    }

    public function update(
        AiChatConversation $conversation,
        UpdateConversationRequest $request,
        UpdateAction $action,
    ): RedirectResponse {
        $action($conversation, $request->validated());

        return redirect()
            ->route('ai-chat.conversations.show', $conversation)
            ->with('success', 'タイトルを更新しました。');
    }

    public function destroy(
        AiChatConversation $conversation,
        Request $request,
        DestroyAction $action,
    ): RedirectResponse {
        $this->authorize('delete', $conversation);

        $latest = $action($request->user(), $conversation);

        return $latest !== null
            ? redirect()->route('ai-chat.conversations.show', $latest)->with('success', '会話を削除しました。')
            : redirect()->route('ai-chat.index')->with('success', '会話を削除しました。');
    }
}
