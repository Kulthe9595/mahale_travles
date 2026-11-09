<?php

namespace App\Enums;

enum TripStatus: string
{
    case Booked = 'booked';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
