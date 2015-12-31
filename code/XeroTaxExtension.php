<?php

class XeroTaxExtension_Item extends DataExtension
{

    private static $db = array(
        'XeroTaxType' => 'Varchar(255)',
        'XeroTaxRate' => 'Decimal(19,4)'
    );
}

class XeroTaxExtension_Modification extends DataExtension
{

    private static $db = array(
        'XeroTaxType' => 'Varchar(255)',
        'XeroTaxRate' => 'Decimal(19,4)'
    );
}
