<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DriverController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Drivers/Index', [
            'drivers' => Driver::query()->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Drivers/Form', ['driver' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Driver::create($this->validated($request));

        return redirect('/drivers')->with('success', 'Driver added.');
    }

    public function edit(Driver $driver): Response
    {
        return Inertia::render('Drivers/Form', ['driver' => $driver]);
    }

    public function update(Request $request, Driver $driver): RedirectResponse
    {
        $driver->update($this->validated($request));

        return redirect('/drivers')->with('success', 'Driver updated.');
    }

    public function destroy(Driver $driver): RedirectResponse
    {
        if (method_exists($driver, 'trips') && $driver->trips()->exists()) {
            return back()->with('error', 'This driver is used in bills. Mark them inactive instead.');
        }
        $driver->delete();

        return redirect('/drivers')->with('success', 'Driver deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'licence_no' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
        ]);
    }
}
