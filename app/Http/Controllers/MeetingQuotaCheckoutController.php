<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MeetingQuota\CheckoutStoreRequest;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\UseCases\MeetingQuota\CreateCheckoutAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * 受講生向け追加面談パックの購入フローを提供する。
 */
final class MeetingQuotaCheckoutController extends Controller
{
    public function index(): View
    {
        return view('meeting-quota.checkout-select', [
            'plans' => MeetingPack::query()->published()->ordered()->get(),
        ]);
    }

    public function store(CheckoutStoreRequest $request, CreateCheckoutAction $action): RedirectResponse
    {
        $validated = $request->validated();
        $pack = MeetingPack::query()->published()->findOrFail($validated['meeting_pack_id']);

        try {
            $checkout = $action($request->user(), $pack);
        } catch (Throwable $exception) {
            Log::warning('Stripe Checkout Sessionの作成に失敗しました。', [
                'user_id' => $request->user()->id,
                'meeting_pack_id' => $pack->id,
                'exception' => $exception,
            ]);

            return back()->with('error', '決済画面を開けませんでした。時間を空けて再度お試しください。');
        }

        return redirect()->away($checkout['url']);
    }

    public function success(Request $request): View
    {
        $sessionId = $request->query('session_id');
        abort_unless(is_string($sessionId) && $sessionId !== '', 404);

        $payment = Payment::query()
            ->with('meetingPack')
            ->where('user_id', $request->user()->id)
            ->where('stripe_checkout_session_id', $sessionId)
            ->firstOrFail();

        return view('meeting-quota.success', ['payment' => $payment]);
    }
}
