<?php

class XeroTaxModification extends Modification {

	private static $defaults = array(
		'SubTotalModifier' => false,
		'SortOrder' => 150
	);

	public function add($order, $value = null) {

		SS_Log::log(new Exception(print_r($order, true)), SS_Log::NOTICE);

		// Go through the items and modifications in the order and apply a tax rate to them
		// Calculate tax based on rates for each item and modification
		
		$items = $order->Items();
		if ($items && $items->exists()) foreach ($items as $item) {
			$item->applyTaxRate();
		}

		$mods = $order->Modifications();
		if ($mods && $mods->exists()) foreach ($mods as $mod) {
			$mod->applyTaxRate();
		}

		
	}

	public function getFormFields() {
		// Get the form fields for the order form
	}

}