<?php

namespace App\Http\Controllers;

use App\Billing\BillCalculator;
use App\Http\Requests\BillPreviewRequest;
use App\Models\BusinessSetting;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/** Live total for the bill form: same calculator the saved bill uses. */
class BillPreviewController extends Controller
{
    public function __invoke(BillPreviewRequest $request, BillCalculator $calculator): JsonResponse
    {
        $trip = (new Trip)->fill($request->tripAttributes());
        $trip->setRelation('customer', \App\Models\Customer::find($trip->customer_id));

        try {
            $result = $calculator->calculate($trip->toBillInput(BusinessSetting::current()));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result->toArray());
    }
}
