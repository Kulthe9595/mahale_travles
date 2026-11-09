<?php

namespace App\Http\Requests;

class BillPreviewRequest extends TripRequest
{
    protected bool $requireEndDetails = true;
}
