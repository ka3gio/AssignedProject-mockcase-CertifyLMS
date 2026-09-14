<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * 認証ユーザー本人の DatabaseNotification 一覧と既読化を提供する。
 *
 * 通知 ID は UUID で推測困難だが、個別既読化では notifiable も照合し、他人の通知を
 * Route Model Binding で指定されても状態を変更しない。通知の遷移先は内部相対 URL のみ許可する。
 */
final class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $request->query('tab') === 'unread' ? 'unread' : 'all';

        $query = $user->notifications()->latest();

        if ($tab === 'unread') {
            $query->whereNull('read_at');
        }

        return view('notifications.index', [
            'notifications' => $query->paginate(20)->withQueryString(),
            'unreadCount' => $user->unreadNotifications()->count(),
            'tab' => $tab,
        ]);
    }

    public function markAsRead(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $notification->notifiable_id === $user->getKey()
                && $notification->notifiable_type === $user->getMorphClass(),
            403,
        );

        $notification->markAsRead();

        $target = $notification->data['url'] ?? null;

        if (! $this->isInternalPath($target)) {
            return redirect()->route('notifications.index');
        }

        return redirect()->to($target);
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'すべての通知を既読にしました。');
    }

    private function isInternalPath(mixed $target): bool
    {
        if (! is_string($target) || ! str_starts_with($target, '/') || str_starts_with($target, '//')) {
            return false;
        }

        $parts = parse_url($target);

        return is_array($parts)
            && ! isset($parts['scheme'])
            && ! isset($parts['host']);
    }
}
