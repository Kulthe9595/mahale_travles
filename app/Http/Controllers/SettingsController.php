<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Support\Gstin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(): Response
    {
        Gate::authorize('manage-settings');
        $settings = BusinessSetting::current();

        return Inertia::render('Settings/Edit', [
            'settings' => $settings,
            'logoUrl' => $settings->logo_path ? Storage::disk('public')->url($settings->logo_path) : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manage-settings');

        $request->merge(['gstin' => Gstin::normalize($request->input('gstin'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'gstin' => ['nullable', Gstin::RULE],
            'state' => ['required', 'string', 'max:60'],
            'sac_code' => ['required', 'string', 'max:10'],
            'gst_rate' => ['required', 'numeric', 'min:0', 'max:28'],
            'min_km_per_day' => ['required', 'integer', 'min:0', 'max:1000'],
            'invoice_prefix' => ['required', 'alpha_num', 'max:10'],
            'bank_details' => ['nullable', 'string', 'max:500'],
            'upi_id' => ['nullable', 'string', 'max:100'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'max:1024'],
        ], [
            'gstin.regex' => Gstin::MESSAGE,
        ]);

        $settings = BusinessSetting::current();

        if ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }
        unset($data['logo']);
        $data['invoice_prefix'] = strtoupper($data['invoice_prefix']);

        $settings->update($data);

        return redirect('/settings')->with('success', 'Settings saved.');
    }
}
