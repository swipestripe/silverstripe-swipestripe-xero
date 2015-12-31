<?php

class XeroExtension_Customer extends DataExtension
{

    private static $db = array(
        'XeroContactID' => 'Varchar(255)'
    );
}

class XeroExtension_Order extends DataExtension
{

    private static $db = array(
        'XeroInvoiceID' => 'Varchar(255)'
    );
}

class XeroExtension_Payment extends DataExtension
{

    private static $db = array(
        'XeroPaymentID' => 'Varchar(255)'
    );
}
