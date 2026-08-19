<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $order = new Order();
        $order->user_id = $request->user_id;
        $order->total = $request->total;
        $order->payment_method = $request->payment_method;
        $order->save();

        if ($request->payment_method === 'credit_card') {
            $stripe = new \Stripe\StripeClient(
                config('services.stripe.secret')
            );

            $stripe->charges->create([
                'amount' => $request->total * 100,
                'currency' => 'usd',
                'source' => $request->card_token,
            ]);

            $order->status = 'paid';
            $order->save();
        } elseif ($request->payment_method === 'wallet') {
            $user = User::find($request->user_id);

            if ($user->wallet_balance < $request->total) {
                return response()->json([
                    'message' => 'Insufficient wallet balance',
                ], 422);
            }

            $user->wallet_balance -= $request->total;
            $user->save();

            $order->status = 'paid';
            $order->save();
        } elseif ($request->payment_method === 'cod') {
            $order->status = 'pending_cod';
            $order->save();
        }

        return response()->json($order);
    }
}
