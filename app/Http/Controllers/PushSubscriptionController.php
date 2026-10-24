<?php

namespace App\Http\Controllers;

use App\Services\WebPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The three calls public/assets/js/notifications.js makes when a member turns on
 * phone alerts: register this browser's push endpoint, report what this server and
 * this member have, and forget the endpoint again.
 *
 * These are ordinary authenticated web routes, so the session cookie and the CSRF
 * token do the same job they do everywhere else - no webhook, nothing to exempt.
 * The page cannot forge a subscription for somebody else because the endpoint is
 * always filed under Auth::id().
 *
 * See docs/PUSH.md.
 */
class PushSubscriptionController extends Controller
{
    /** @var \App\Services\WebPushService */
    protected $push;

    public function __construct(WebPushService $push)
    {
        $this->push = $push;

        $this->middleware('auth');
    }

    /**
     * What the page needs to decide what to show: is the server able to push at
     * all, does this member already have a device on file, and what is the VAPID
     * public key (null when there is none, which is how the page knows to stay
     * quiet instead of asking for a permission it cannot use).
     */
    public function status()
    {
        $memberId = (int) Auth::id();
        $devices = $this->push->devicesFor($memberId);

        return response()->json([
            'configured' => $this->push->isConfigured(),
            'devices' => $devices,
            'subscribed' => $devices > 0,
            'publicKey' => $this->push->publicKey(),
        ]);
    }

    /**
     * Store (or refresh) the endpoint the browser just subscribed with.
     *
     * The shape is exactly PushSubscription.toJSON(): endpoint plus keys.p256dh and
     * keys.auth, which are what the sender encrypts a message with.
     */
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => 'required|string|max:1000',
            'keys.p256dh' => 'required|string|max:191',
            'keys.auth' => 'required|string|max:191',
            'contentEncoding' => 'nullable|string|max:20',
        ]);

        if (! $this->push->isConfigured()) {
            return response()->json([
                'subscribed' => false,
                'message' => 'Phone alerts are not switched on for this server yet.',
            ], 409);
        }

        $stored = $this->push->subscribe((int) Auth::id(), [
            'endpoint' => $data['endpoint'],
            'p256dh' => $data['keys']['p256dh'],
            'auth' => $data['keys']['auth'],
            'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
        ], $request->userAgent());

        return response()->json([
            'subscribed' => $stored,
            'devices' => $this->push->devicesFor((int) Auth::id()),
            'message' => $stored
                ? 'This device will now get alerts even when the app is closed.'
                : 'That subscription could not be stored.',
        ], $stored ? 200 : 422);
    }

    /**
     * Remove this device (the page does this when the browser revoked the
     * subscription, so the outbox stops queueing for a dead endpoint).
     */
    public function unsubscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => 'required|string|max:1000',
        ]);

        $removed = $this->push->unsubscribe((int) Auth::id(), $data['endpoint']);

        return response()->json([
            'subscribed' => false,
            'removed' => $removed,
            'devices' => $this->push->devicesFor((int) Auth::id()),
        ]);
    }
}
