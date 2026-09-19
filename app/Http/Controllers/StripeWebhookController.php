<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\StripeApiGateway;
use App\UseCases\MeetingQuota\HandleStripeWebhookAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

final class StripeWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        StripeApiGateway $stripe,
        HandleStripeWebhookAction $action,
    ): Response {
        try {
            $event = $stripe->constructWebhookEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature', ''),
            );
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response('', 400);
        }

        $action($event);

        return response('', 200);
    }
}
