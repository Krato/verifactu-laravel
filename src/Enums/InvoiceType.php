<?php

namespace Krato\Verifactu\Enums;

enum InvoiceType: string
{
    case F1 = 'F1'; // Factura (art. 6, 7.2 y 7.3 del RD 1619/2012)
    case F2 = 'F2'; // Factura simplificada (art. 6.1.d y 7.1 del RD 1619/2012)
}
