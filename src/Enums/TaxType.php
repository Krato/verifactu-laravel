<?php

namespace Krato\Verifactu\Enums;

enum TaxType: string
{
    case IVA = '01';   // Impuesto sobre el Valor Añadido
    case IGIC = '02';  // Impuesto General Indirecto Canario
    case IPSI = '03';  // Impuesto sobre la Producción, los Servicios y la Importación
}
