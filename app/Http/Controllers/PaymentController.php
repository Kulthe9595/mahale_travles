<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMode;
use App\Models\Payment;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function store(Request $request, Trip $trip): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'paid_on' => ['required', 'date'],
            'mode' => ['required', Rule::enum(PaymentMode::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['amount.gt' => 'Amount must be more than zero.']);

        $trip->payments()->create($data);

        return redirect("/trips/{$trip->id}")->with('success', 'Payment recorded.');
    }

    public function destroy(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $tripId = $payment->trip_id;
        $payment->delete();

        return redirect("/trips/{$tripId}")->with('success', 'Payment removed.');
    }
}
