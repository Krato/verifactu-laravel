<?php

namespace Krato\Verifactu\Enums;

enum TaxRegime: string
{
    case General = '01';                           // Operación en régimen general
    case Export = '02';                            // Exportación
    case SpecialGoods = '03';                      // Régimen especial de bienes usados
    case InvestmentGold = '04';                    // Régimen especial de oro de inversión
    case TravelAgencies = '05';                    // Régimen especial de agencias de viaje
    case EntityGroups = '06';                      // Régimen especial de grupos de entidades en IVA
    case CashBasis = '07';                         // Régimen especial del criterio de caja
    case IPSI_IGIC = '08';                         // Operaciones sujetas a IPSI/IGIC
    case IntraEUSupplies = '09';                   // Facturación de prestaciones de servicios intracomunitarios
    case ReverseCharge = '10';                     // Cobros por cuenta de terceros
    case SimplifiedRegime = '11';                  // Operaciones en recargo de equivalencia
    case EqualizationCharge = '12';                // Operaciones en régimen simplificado
    case NotSubject = '14';                        // Facturas con IVA pendiente de devengo
    case NotSubjectLocationRules = '15';           // Factura con IVA pendiente de devengo - operaciones de tracto sucesivo
}
