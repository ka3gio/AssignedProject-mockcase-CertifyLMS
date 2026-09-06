<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QaReply\StoreRequest;
use App\Http\Requests\QaReply\UpdateRequest;
use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaReply\DestroyAction;
use App\UseCases\QaReply\StoreAction;
use App\UseCases\QaReply\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Q&A回答の投稿・編集・削除を提供する。 */
class QaReplyController extends Controller
{
    public function store(
        QaThread $thread,
        StoreRequest $request,
        StoreAction $action,
    ): RedirectResponse {
        $action($request->user(), $thread, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を投稿しました。');
    }

    public function edit(QaThread $thread, QaReply $reply): View
    {
        $this->authorize('update', $reply);

        return view('qa-thread.reply-edit', compact('thread', 'reply'));
    }

    public function update(
        QaThread $thread,
        QaReply $reply,
        UpdateRequest $request,
        UpdateAction $action,
    ): RedirectResponse {
        $action($reply, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を更新しました。');
    }

    public function destroy(
        QaThread $thread,
        QaReply $reply,
        DestroyAction $action,
    ): RedirectResponse {
        $this->authorize('delete', $reply);

        $action($reply);

        $route = request()->routeIs('admin.*')
            ? 'admin.qa-board.show'
            : 'qa-board.show';

        return redirect()
            ->route($route, $thread)
            ->with('success', '回答を削除しました。');
    }
}
