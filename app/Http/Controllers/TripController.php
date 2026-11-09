<?php

namespace App\Http\Controllers;

use App\Http\Requests\TripRequest;
use App\Models\BusinessSetting;
use App\Models\Car;
use App\Models\Driver;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Trips/Index', [
            'trips' => Trip::with(['customer', 'car'])->latest('start_at')->paginate(25),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Trips/Form', $this->formProps(null));
    }

    public function store(TripRequest $request): RedirectResponse
    {
        $trip = Trip::create($request->tripAttributes());

        return redirect("/trips/{$trip->id}")->with('success', 'Trip saved.');
    }

    public function show(Trip $trip): Response
    {
        return Inertia::render('Trips/Show', [
            'trip' => $trip->load(['customer', 'car', 'driver']),
        ]);
    }

    public function edit(Trip $trip): Response
    {
        return Inertia::render('Trips/Form', $this->formProps($trip->load('customer')));
    }

    public function update(TripRequest $request, Trip $trip): RedirectResponse
    {
        $trip->update($request->tripAttributes());

        return redirect("/trips/{$trip->id}")->with('success', 'Trip updated.');
    }

    private function formProps(?Trip $trip): array
    {
        $settings = BusinessSetting::current();
        $keepIds = fn (string $column) => $trip?->{$column};

        return [
            'trip' => $trip,
            'cars' => Car::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $keepIds('car_id')))
                ->orderBy('registration_no')
                ->get(['id', 'registration_no', 'model', 'rate_per_km', 'rate_per_day']),
            'drivers' => Driver::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $keepIds('driver_id')))
                ->orderBy('name')
                ->get(['id', 'name', 'phone']),
            'settings' => [
                'min_km_per_day' => (int) $settings->min_km_per_day,
                'gst_rate' => $settings->gst_rate,
                'gst_registered' => (bool) $settings->gstin,
            ],
        ];
    }
}
