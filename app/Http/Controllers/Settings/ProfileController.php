<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingsProfile\StoreAvatarRequest;
use App\Http\Requests\SettingsProfile\UpdateRequest;
use App\UseCases\SettingsProfile\DestroyAvatarAction;
use App\UseCases\SettingsProfile\StoreAvatarAction;
use App\UseCases\SettingsProfile\UpdateProfileAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 全ロール共通の本人プロフィール表示・更新、アバター画像変更 Controller。
 *
 * URL にユーザー ID を持たず、対象は常に認証ユーザー本人に固定する。
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.profile', [
            'user' => $request->user(),
        ]);
    }

    public function update(UpdateRequest $request, UpdateProfileAction $action): RedirectResponse
    {
        $action($request->user(), $request->validated());

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'プロフィールを更新しました。');
    }

    public function storeAvatar(StoreAvatarRequest $request, StoreAvatarAction $action): RedirectResponse
    {
        $action($request->user(), $request->file('avatar'));

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'アイコン画像を更新しました。');
    }

    public function destroyAvatar(Request $request, DestroyAvatarAction $action): RedirectResponse
    {
        $action($request->user());

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'アイコン画像を削除しました。');
    }
}
