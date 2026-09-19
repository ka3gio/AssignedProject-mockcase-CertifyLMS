<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\GoogleCalendarCredential;
use App\Services\Contracts\GoogleCalendarGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class GoogleCalendarController extends Controller
{
    public function redirect(Request $request, GoogleCalendarGateway $gateway): RedirectResponse
    {
        $state = Str::random(64);
        $request->session()->put('google_calendar_oauth_state', $state);

        return redirect()->away($gateway->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleCalendarGateway $gateway): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_calendar_oauth_state');
        $actualState = $request->query('state');

        abort_unless(
            is_string($expectedState)
                && is_string($actualState)
                && hash_equals($expectedState, $actualState),
            403,
        );

        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()
                ->route('settings.availability.index')
                ->with('error', 'Google カレンダーとの連携は完了しませんでした。');
        }

        try {
            $token = $gateway->exchangeCode((string) $request->query('code'));
            $calendarId = $gateway->primaryCalendarId($token);
            $existing = $request->user()->googleCredential;
            $created = (int) ($token['created'] ?? now()->timestamp);
            $expiresIn = (int) ($token['expires_in'] ?? 3600);

            GoogleCalendarCredential::query()->updateOrCreate(
                ['coach_id' => $request->user()->id],
                [
                    'access_token' => (string) $token['access_token'],
                    'refresh_token' => $token['refresh_token'] ?? $existing?->refresh_token,
                    'token_expires_at' => Carbon::createFromTimestamp($created)->addSeconds($expiresIn),
                    'calendar_id' => $calendarId,
                    'connected_at' => now(),
                ],
            );
        } catch (Throwable $exception) {
            Log::warning('Google カレンダー連携に失敗しました。', [
                'coach_id' => $request->user()->id,
                'exception' => $exception,
            ]);

            return redirect()
                ->route('settings.availability.index')
                ->with('error', 'Google カレンダーとの連携に失敗しました。時間を空けて再度お試しください。');
        }

        return redirect()
            ->route('settings.availability.index')
            ->with('success', 'Google カレンダーと連携しました。');
    }

    public function destroy(Request $request, GoogleCalendarGateway $gateway): RedirectResponse
    {
        $credential = $request->user()->googleCredential;

        if ($credential !== null) {
            try {
                $gateway->revoke($credential);
            } catch (Throwable $exception) {
                Log::warning('Google OAuth トークンの失効に失敗しました。', [
                    'coach_id' => $request->user()->id,
                    'exception' => $exception,
                ]);
            } finally {
                $credential->delete();
            }
        }

        return redirect()
            ->route('settings.availability.index')
            ->with('success', 'Google カレンダーとの連携を解除しました。');
    }
}
