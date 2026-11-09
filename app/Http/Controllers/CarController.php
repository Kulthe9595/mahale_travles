<?php

namespace App\Http\Controllers;

use App\Models\Car;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CarController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Cars/Index', [
            'cars' => Car::query()->orderByDesc('is_active')->orderBy('registration_no')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Cars/Form', ['car' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Car::create($this->validated($request));

        return redirect('/cars')->with('success', 'Car added.');
    }

    public function edit(Car $car): Response
    {
        return Inertia::render('Cars/Form', ['car' => $car]);
    }

    public function update(Request $request, Car $car): RedirectResponse
    {
        $car->update($this->validated($request, $car));

        return redirect('/cars')->with('success', 'Car updated.');
    }

    public function destroy(Car $car): RedirectResponse
    {
        if ($this->isUsed($car)) {
            return back()->with('error', 'This car is used in bills. Mark it inactive instead.');
        }
        $car->delete();

        return redirect('/cars')->with('success', 'Car deleted.');
    }

    private function isUsed(Car $car): bool
    {
        return $car->trips()->exists();
    }

    private function validated(Request $request, ?Car $car = null): array
    {
        $request->merge(['registration_no' => Car::normalizeRegistration($request->input('registration_no'))]);

        return $request->validate([
            'registration_no' => ['required', 'string', 'min:4', 'max:20', Rule::unique('cars')->ignore($car)],
            'model' => ['required', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:30'],
            'seats' => ['nullable', 'integer', 'min:1', 'max:60'],
            'rate_per_km' => ['required', 'numeric', 'min:0', 'max:100000'],
            'rate_per_day' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'is_active' => ['boolean'],
        ]);
    }
}
