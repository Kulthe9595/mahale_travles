<?php

namespace App\Http\Controllers;

use App\Enums\ShareChannel;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ShareController extends Controller
{
    public function __construct(private readonly ShareService $share) {}

    public function invoice(Request $request, Invoice $invoice): JsonResponse
    {
        $channel = $this->channel($request);
        $recipient = $channel === ShareChannel::WhatsappLink ? $this->share->whatsappNumber($invoice) : null;
        $log = $this->share->log($invoice, $channel, $recipient);

        return response()->json(['ok' => true, 'id' => $log->id]);
    }

    public function payment(Request $request, Payment $payment): JsonResponse
    {
        $log = $this->share->log($payment, $this->channel($request));

        return response()->json(['ok' => true, 'id' => $log->id]);
    }

    public function email(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'email', 'max:150']]);

        try {
            $this->share->email($invoice, $data['to']);
        } catch (TransportExceptionInterface $e) {
            Log::warning('Bill email failed', ['invoice' => $invoice->id, 'error' => $e->getMessage()]);

            return redirect("/trips/{$invoice->trip_id}")
                ->with('error', 'Email could not be sent. Check the mail settings (Gmail app password).');
        }

        return redirect("/trips/{$invoice->trip_id}")->with('success', "Bill emailed to {$data['to']}.");
    }

    public function link(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $invoice->update(['share_enabled' => $data['enabled']]);

        return redirect("/trips/{$invoice->trip_id}")
            ->with('success', $data['enabled'] ? 'Bill link turned on.' : 'Bill link turned off.');
    }

    private function channel(Request $request): ShareChannel
    {
        $data = $request->validate(['channel' => ['required', Rule::enum(ShareChannel::class)]]);

        return ShareChannel::from($data['channel']);
    }
}
