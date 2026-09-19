<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * 認証ユーザー本人の DatabaseNotification 一覧・詳細と既読化を提供する。
 *
 * 通知 ID は UUID で推測困難だが、個別既読化では notifiable も照合し、他人の通知を
 * Route Model Binding で指定されても状態を変更しない。通知の遷移先は内部相対 URL のみ許可する。
 */
final class NotificationController extends Controller
{
    private const POPOVER_LIMIT = 20;

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

    public function show(Request $request, DatabaseNotification $notification): View
    {
        $user = $request->user();

        abort_unless(
            $notification->notifiable_id === $user->getKey()
                && $notification->notifiable_type === $user->getMorphClass(),
            403,
        );

        return view('notifications.show', compact('notification'));
    }

    public function markAsRead(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        $this->authorizeOwner($request, $notification);

        $notification->markAsRead();

        return redirect()->to($this->notificationTarget($notification));
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'すべての通知を既読にしました。');
    }

    public function apiIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $user->notifications()->latest();

        if ($request->query('tab') === 'unread') {
            $query->whereNull('read_at');
        }

        $notifications = $query
            ->limit(self::POPOVER_LIMIT)
            ->get()
            ->map(function (DatabaseNotification $notification): array {
                $data = is_array($notification->data) ? $notification->data : [];

                return [
                    'id' => $notification->id,
                    'title' => $data['title'] ?? '通知',
                    'message' => $data['message'] ?? ($data['body_preview'] ?? ''),
                    'url' => $this->notificationTarget($notification),
                    'is_unread' => $notification->read_at === null,
                    'created_at' => $notification->created_at?->toIso8601String(),
                    'created_at_human' => $notification->created_at?->diffForHumans() ?? '',
                ];
            });

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function apiMarkAsRead(Request $request, DatabaseNotification $notification): JsonResponse
    {
        $this->authorizeOwner($request, $notification);

        $notification->markAsRead();

        return response()->json([
            'redirect_url' => $this->notificationTarget($notification),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function apiMarkAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['unread_count' => 0]);
    }

    private function authorizeOwner(Request $request, DatabaseNotification $notification): void
    {
        $user = $request->user();

        abort_unless(
            $notification->notifiable_id === $user->getKey()
                && $notification->notifiable_type === $user->getMorphClass(),
            403,
        );
    }

    private function notificationTarget(DatabaseNotification $notification): string
    {
        $target = $notification->data['url'] ?? null;

        return $this->isInternalPath($target)
            ? $target
            : route('notifications.index', absolute: false);
    }

    private function isInternalPath(mixed $target): bool
    {
        if (! is_string($target)
            || ! str_starts_with($target, '/')
            || str_starts_with($target, '//')
            || str_contains($target, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $target) === 1
        ) {
            return false;
        }

        $parts = parse_url($target);

        return is_array($parts)
            && ! isset($parts['scheme'])
            && ! isset($parts['host']);
    }
}
