<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Trip;
use App\Support\Money;
use App\Support\Gstin;
use App\Support\IndianStates;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q'));

        return Inertia::render('Customers/Index', [
            'customers' => $this->searchQuery($q)->orderBy('name')->paginate(25)->withQueryString(),
            'filters' => ['q' => $q],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));

        return response()->json(
            $this->searchQuery($q)->orderBy('name')->limit(10)
                ->get(['id', 'name', 'phone', 'email', 'state', 'gstin'])
        );
    }

    public function create(): Response
    {
        return Inertia::render('Customers/Form', ['customer' => null, 'states' => IndianStates::names()]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $customer = Customer::create($this->validated($request));

        if ($request->expectsJson()) {
            return response()->json(['customer' => $customer], 201);
        }

        return redirect('/customers')->with('success', 'Customer added.');
    }

    public function show(Customer $customer): Response
    {
        $trips = Trip::query()
            ->withBilling()
            ->where('customer_id', $customer->id)
            ->whereRaw(Trip::SQL_INVOICE_TOTAL.' is not null')
            ->with('car:id,registration_no')
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->get();

        $bills = $trips->map(function (Trip $t) {
            $total = Money::toPaise($t->invoice_total);
            $paid = Money::toPaise($t->paid_total);

            return [
                'trip_id' => $t->id,
                'invoice_no' => $t->invoice_no,
                'date' => $t->start_at->format('d M Y'),
                'car' => $t->car->registration_no,
                'route' => trim(($t->pickup ?: '—').' → '.($t->drop ?: '—')),
                'total' => Money::toDecimal($total),
                'paid' => Money::toDecimal($paid),
                'balance' => Money::toDecimal(max(0, $total - $paid)),
                'balance_paise' => max(0, $total - $paid),
            ];
        });

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'bills' => $bills->map(fn ($b) => collect($b)->except('balance_paise'))->values(),
            'outstanding' => Money::toDecimal((int) $bills->sum('balance_paise')),
        ]);
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('Customers/Form', ['customer' => $customer, 'states' => IndianStates::names()]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request));

        return redirect('/customers')->with('success', 'Customer updated.');
    }

    private function searchQuery(string $q)
    {
        $query = Customer::query();
        if ($q === '') {
            return $query;
        }

        $digits = preg_replace('/\D/', '', $q);

        return $query->where(function ($w) use ($q, $digits) {
            $w->where('name', 'like', "%{$q}%");
            if (strlen($digits) >= 3) {
                $w->orWhere('phone_digits', 'like', "%{$digits}%");
            }
        });
    }

    private function validated(Request $request): array
    {
        $request->merge([
            'gstin' => Gstin::normalize($request->input('gstin')),
            'state' => $request->filled('state') ? trim($request->input('state')) : 'Maharashtra',
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'state' => ['required', Rule::in(IndianStates::names())],
            'gstin' => ['nullable', Gstin::RULE],
        ], [
            'gstin.regex' => Gstin::MESSAGE,
            'state.in' => 'Choose a state from the list.',
        ]);

        if (! IndianStates::gstinMatches($data['gstin'] ?? null, $data['state'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'gstin' => 'This GSTIN belongs to a different state than the one selected.',
            ]);
        }

        return $data;
    }
}
