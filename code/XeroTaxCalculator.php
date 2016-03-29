<?php

/**
 * Calculate tax for an Order and apply tax rates to Items and Modifications based on Xero tax types. Logic for applying
 * tax rates will likely change depending on application.
 *
 * Injected into XeroTaxModification, can change with config e.g:
 * ---
 * name: swipestripe_xero_tax
 * ---
 * XeroTaxModification:
 *   dependencies:
 *     taxCalculator: %$XeroTaxCalculator_NZ
 *
 * @see http://doc.silverstripe.org/framework/en/reference/injector
 */
abstract class XeroTaxCalculator
{

    /**
     * Apply a tax rate to the Item
     * 
     * @param  Item   $item
     */
    abstract public function applyItemTaxRate(Item $item);

    /**
     * Apply a tax rate to the Order Modification
     * 
     * @param  Modification $mod
     */
    abstract public function applyModificationTaxRate(Modification $mod);

    /**
     * Calculate the tax component based on tax rates for the items and modifications in the order
     * 
     * @param  Order  $order
     * @return Price  The tax amount for the order
     */
    public function calculate(Order $order)
    {
        $taxAmount = 0;
        $shopConfig = ShopConfig::current_shop_config();

        $items = $order->Items();
        if ($items && $items->exists()) {
            foreach ($items as $item) {
                $taxAmount += $item->Total()->getAmount() * ($item->XeroTaxRate / 100);
            }
        }

        $mods = $order->Modifications();
        if ($mods && $mods->exists()) {
            foreach ($mods as $mod) {
                $taxAmount += $mod->Amount()->getAmount() * ($mod->XeroTaxRate / 100);
            }
        }
        
        $amount = new Price();
        $amount->setAmount($taxAmount);
        $amount->setCurrency($shopConfig->BaseCurrency);
        $amount->setSymbol($shopConfig->BaseCurrencySymbol);
        return $amount;
    }
}

/**
 * Apply NZ tax rates to Orders being sent to NZ and no tax to orders shipped elsewhere.
 */
class XeroTaxCalculator_NZ extends XeroTaxCalculator
{

    public function applyItemTaxRate(Item $item)
    {
        $order = $item->Order();

        // Orders shipped within New Zealand have tax applied
        if ($order && $order->exists() && $order->ShippingCountryCode == 'NZ') {
            $item->XeroTaxType = 'OUTPUT2';
            $item->XeroTaxRate = 15.00;
        } else {
            $item->XeroTaxType = 'NONE';
            $item->XeroTaxRate = 0.00;
        }

        $item->write();
    }

    public function applyModificationTaxRate(Modification $mod)
    {
        if ($mod->SubTotalModifier) {
            $order = $mod->Order();

            // Orders shipped within New Zealand have tax applied
            if ($order && $order->exists() && $order->ShippingCountryCode == 'NZ') {
                $mod->XeroTaxType = 'OUTPUT2';
                $mod->XeroTaxRate = 15.00;
            } else {
                $mod->XeroTaxType = 'NONE';
                $mod->XeroTaxRate = 0.00;
            }

            $mod->write();
        }
    }
}
