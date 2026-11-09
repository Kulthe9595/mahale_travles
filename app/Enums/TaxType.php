<?php

namespace App\Enums;

enum TaxType: string
{
    case CgstSgst = 'cgst_sgst';
    case Igst = 'igst';
    case None = 'none';
}
