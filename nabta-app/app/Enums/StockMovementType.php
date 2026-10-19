<?php

namespace App\Enums;

enum StockMovementType: string
{
    case PurchaseReceipt = 'purchase_receipt';
    case PosSale = 'pos_sale';
    case OnlineSale = 'online_sale';

    /**
     * Puts back stock that an online order reserved but never paid for — a
     * declined or abandoned Paymob payment. Not a customer return: the goods
     * never left the shelf.
     */
    case OnlineSaleReleased = 'online_sale_released';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case CustomerReturn = 'customer_return';
    case SupplierReturn = 'supplier_return';
    case Adjustment = 'adjustment';

    /**
     * Natural weight loss — moisture, roasting, repacking. Buying a 50kg sack
     * and selling 49.2kg is normal here, not theft. It is its own type so it
     * never pollutes the variance report used to find real problems.
     */
    case Shrinkage = 'shrinkage';

    public function label(): string
    {
        return __(match ($this) {
            self::PurchaseReceipt => 'استلام مشتريات',
            self::PosSale => 'بيع كاشير',
            self::OnlineSale => 'بيع أونلاين',
            self::OnlineSaleReleased => 'إلغاء حجز أونلاين',
            self::TransferOut => 'تحويل صادر',
            self::TransferIn => 'تحويل وارد',
            self::CustomerReturn => 'مرتجع عميل',
            self::SupplierReturn => 'مرتجع مورد',
            self::Adjustment => 'تسوية جرد',
            self::Shrinkage => 'هالك طبيعي',
        });
    }

    /** Whether this type adds to stock. `adjustment` can go either way. */
    public function isInbound(): bool
    {
        return in_array($this, [
            self::PurchaseReceipt,
            self::TransferIn,
            self::CustomerReturn,
            self::OnlineSaleReleased,
        ], true);
    }

    /** @return array<string, string> value => Arabic label, for form selects. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
