<?php
/**
 * Apply tax rates according to Xero tax types for each Item and Modification in the Order. Uses DI to inject a tax
 * calculator that can be replaced based on the rules for applying tax rates.
 * 
 * Can change the dependencies in yml config file e.g:
 * ---
 * name: mysite_swipestripe_xero
 * ---
 * XeroTaxModification:
 *   dependencies:
 *     taxCalculator: %$XeroTaxCalculator_NZ
 */
class XeroTaxModification extends Modification
{

    private static $defaults = array(
        'SubTotalModifier' => false,
        'SortOrder' => 150
    );

    public $taxCalculator;
    /** @config */
    private static $dependencies = array(
        'taxCalculator' => '%$XeroTaxCalculator'
    );

    /**
     * Add a XeroTaxModification to the order by applying tax rates to Items and Modifications in the order using 
     * the taxCalculator.
     * 
     * @param Order $order
     * @param Mixed $value Unused
     */
    public function add($order, $value = null)
    {

        // Go through the items and modifications in the order and apply a tax rate to them
        // Calculate tax based on rates for each item and modification

        $items = $order->Items();
        if ($items && $items->exists()) {
            foreach ($items as $item) {
                $this->taxCalculator->applyItemTaxRate($item);
            }
        }

        $mods = $order->Modifications();
        if ($mods && $mods->exists()) {
            foreach ($mods as $mod) {
                $this->taxCalculator->applyModificationTaxRate($mod);
            }
        }

        // Create new modification using rates for each item and modification in the order
        $taxAmount = $this->taxCalculator->calculate($order);

        $mod = new XeroTaxModification();
        $mod->Price = $taxAmount->getAmount();
        $mod->Description = _t('Xero.TAX', 'Tax');
        $mod->OrderID = $order->ID;
        $mod->Value = null;
        $mod->write();
    }

    /**
     * Get the form fields for the OrderForm.
     * 
     * @return FieldList List of fields
     */
    public function getFormFields()
    {
        $fields = new FieldList();

        $field = new XeroTaxModifierField(
            $this,
            _t('Xero.TAX', 'Tax')
        );

        $shopConfig = ShopConfig::current_shop_config();
        $amount = new Price();
        $amount->setAmount($this->Price);
        $amount->setCurrency($shopConfig->BaseCurrency);
        $amount->setSymbol($shopConfig->BaseCurrencySymbol);

        $field->setAmount($amount);
        $fields->push($field);

        if (!$fields->exists()) {
            Requirements::javascript('swipestripe-flatfeetax/javascript/FlatFeeTaxModifierField.js');
        }
        return $fields;
    }
}
