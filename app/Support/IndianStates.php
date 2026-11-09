<?php

namespace App\Support;

/** States and union territories with their GST state codes. */
final class IndianStates
{
    public const CODES = [
        'Andaman and Nicobar Islands' => '35',
        'Andhra Pradesh' => '37',
        'Arunachal Pradesh' => '12',
        'Assam' => '18',
        'Bihar' => '10',
        'Chandigarh' => '04',
        'Chhattisgarh' => '22',
        'Dadra and Nagar Haveli and Daman and Diu' => '26',
        'Delhi' => '07',
        'Goa' => '30',
        'Gujarat' => '24',
        'Haryana' => '06',
        'Himachal Pradesh' => '02',
        'Jammu and Kashmir' => '01',
        'Jharkhand' => '20',
        'Karnataka' => '29',
        'Kerala' => '32',
        'Ladakh' => '38',
        'Lakshadweep' => '31',
        'Madhya Pradesh' => '23',
        'Maharashtra' => '27',
        'Manipur' => '14',
        'Meghalaya' => '17',
        'Mizoram' => '15',
        'Nagaland' => '13',
        'Odisha' => '21',
        'Puducherry' => '34',
        'Punjab' => '03',
        'Rajasthan' => '08',
        'Sikkim' => '11',
        'Tamil Nadu' => '33',
        'Telangana' => '36',
        'Tripura' => '16',
        'Uttar Pradesh' => '09',
        'Uttarakhand' => '05',
        'West Bengal' => '19',
    ];

    /** @return string[] */
    public static function names(): array
    {
        return array_keys(self::CODES);
    }

    public static function gstinMatches(?string $gstin, ?string $state): bool
    {
        if (! $gstin || ! $state || ! isset(self::CODES[$state])) {
            return true;
        }

        return substr($gstin, 0, 2) === self::CODES[$state];
    }
}
