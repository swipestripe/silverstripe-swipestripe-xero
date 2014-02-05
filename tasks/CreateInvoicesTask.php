<?php

require BASE_PATH . '/swipestripe-xero/thirdparty/XeroOAuth.php';

class RemoveDevOrdersTask extends BuildTask {
	
	protected $title = 'Create Xero Invoices';
	
	protected $description = 'Create invoices in Xero for SwipeStripe orders.';

	public function run($request) {

		// TODO: Configuration values
		define ( "XRO_APP_TYPE", "Private" );
		define ( "OAUTH_CALLBACK", "oob" );
		$useragent = "XeroOAuth-PHP Private App Test";

		$signatures = array (
				'consumer_key' => 'NUKLBKLBL0YZLGTRHGFJXRPFKKSFYO',
				'shared_secret' => 'PI45UNDNMTDQAD4G7SBAYOEHWBWUMO',
				// API versions
				'core_version' => '2.0',
				'payroll_version' => '1.0' 
		);

		if (XRO_APP_TYPE == "Private" || XRO_APP_TYPE == "Partner") {
			$signatures ['rsa_private_key'] = BASE_PATH . '/assets/privatekey.pem';
			$signatures ['rsa_public_key'] = BASE_PATH . '/assets/publickey.cer';
		}

		$XeroOAuth = new XeroOAuth ( array_merge ( array (
			'application_type' => XRO_APP_TYPE,
			'oauth_callback' => OAUTH_CALLBACK,
			'user_agent' => $useragent 
		), $signatures ) );

		$initialCheck = $XeroOAuth->diagnostics ();
		$checkErrors = count ( $initialCheck );
		if ($checkErrors > 0) {
			// you could handle any config errors here, or keep on truckin if you like to live dangerously
			foreach ( $initialCheck as $check ) {
				echo 'Error: ' . $check . PHP_EOL;
			}
		} 
		else {

			Session::set('Xero', array (
				'oauth_token' => $XeroOAuth->config ['consumer_key'],
				'oauth_token_secret' => $XeroOAuth->config ['shared_secret'],
				'oauth_session_handle' => '' 
			));

			$oauthSession['oauth_token'] = Session::get('Xero.oauth_token');
			$oauthSession['oauth_token_secret'] = Session::get('Xero.oauth_token_secret');
			$oauthSession['oauth_session_handle'] = Session::get('Xero.oauth_session_handle');

			if (isset ( $oauthSession ['oauth_token'] )) {
				$XeroOAuth->config ['access_token'] = $oauthSession ['oauth_token'];
				$XeroOAuth->config ['access_token_secret'] = $oauthSession ['oauth_token_secret'];
				
				$this->createInvoices($XeroOAuth);
				$this->createPayments($XeroOAuth);
			}
		}
	}

	private function createInvoices($XeroOAuth) {

		$xeroConnection = clone $XeroOAuth;

		// TODO: Configuration values
		$defaultAccountCode = 200;
		$defaultTaxType = 'OUTPUT2';
		$zeroTaxTaype = 'NONE';
		$taxClasses = array(
			'FlatFeeTaxModification'
		);
		$baseCurrency = 'NZD';
		$invoicePrefix = 'WEB-';


		$invoices = array();
		// Orders that have not been sent to Xero and are completed
		$orders = Order::get()->where(" \"XeroInvoiceID\" IS NULL AND \"Status\" != 'Cart'");
		$i = 0;

		if ($orders && $orders->exists()) foreach ($orders as $order) {

			$invoices[$i]['Invoice'] = array(
				'Type' => 'ACCREC',
				'InvoiceNumber' => $invoicePrefix . $order->ID,
				'Contact' => array(
					'Name' => $order->Member()->getName()
				),
				'Date' => $order->OrderedOn,
				'DueDate' => $order->OrderedOn,
				'Status' => 'AUTHORISED',
				'LineAmountTypes' => 'Exclusive',
				'CurrencyCode' => $baseCurrency
			);

			// Line items for each item in the order
			$items = $order->Items();
			if ($items && $items->exists()) foreach ($items as $item) {

				$object = ($item->Variation()) ? $item->Variation() : $item->Product();

				$description = $object->Title;
				if ($object instanceof Variation) {
					$description = strip_tags($object->Product()->Title . ' ' . $object->SummaryOfOptions());
				}

				$invoices[$i]['Invoice']['LineItems'][]['LineItem'] = array(
					'Description' => $description,
					'Quantity' => $item->Quantity,
					'UnitAmount' => $item->Price,
					'AccountCode' => $defaultAccountCode,
					'TaxType' => $defaultTaxType
				);
			}

			// Line items for each order modifier
			$modifications = $order->Modifications();
			if ($modifications && $modifications->exists()) foreach ($modifications as $modification) {

				if ($modification->SubTotalModifier) {
					$invoices[$i]['Invoice']['LineItems'][]['LineItem'] = array(
						"Description" => $modification->Description,
						"Quantity" => 1,
						"UnitAmount" => $modification->Amount()->getAmount(),
						"AccountCode" => $defaultAccountCode,
						'TaxType' => $defaultTaxType
					);
				}
				else if (!in_array(get_class($modification), $taxClasses)) {
					$invoices[$i]['Invoice']['LineItems'][]['LineItem'] = array(
						"Description" => $modification->Description,
						"Quantity" => 1,
						"UnitAmount" => $modification->Amount()->getAmount(),
						"AccountCode" => $defaultAccountCode,
						'TaxType' => $zeroTaxTaype
					);
				}
			}

			$i++;
		}

		// If no data do not send to Xero
		if (empty($invoices)) {
			return;
		}

		$invoicesXML = new SimpleXMLElement("<Invoices></Invoices>");
		$this->arrayToXML($invoices, $invoicesXML);
		$xml = $invoicesXML->asXML();

		$response = $xeroConnection->request('POST', $xeroConnection->url('Invoices', 'core'), array(), $xml);
		if ($xeroConnection->response['code'] == 200) {

			$invoices = $xeroConnection->parseResponse($xeroConnection->response['response'], $xeroConnection->response['format']);
			echo count($invoices->Invoices[0]). " invoice(s) created in this Xero organisation.";

			// Update Orders that have been pushed to Xero so that they are not sent again
			foreach ($invoices->Invoices->Invoice as $invoice) {

				$order = Order::get()
					->filter('ID', str_replace($invoicePrefix, '', $invoice->InvoiceNumber->__toString()))
					->first();

				if ($order && $order->exists()) {
					$order->XeroInvoiceID = $invoice->InvoiceID->__toString();
					$order->write();
				}
			}
		}
		else {
			echo 'Error: ' . $xeroConnection->response['response'] . PHP_EOL;
			SS_Log::log(new Exception(print_r($xeroConnection, true)), SS_Log::NOTICE);
		}
	}

	private function createPayments($XeroOAuth) {

		$xeroConnection = clone $XeroOAuth;

		// TODO: Configuration values
		$defaultAccountPurchasesCode = '090';
		$invoicePrefix = 'WEB-';

		$data = array();
		// Creating payments only for orders that have been created on Xero
		$orders = Order::get()->where(" \"XeroInvoiceID\" IS NOT NULL ");
		$i = 0;

		if ($orders && $orders->exists()) foreach ($orders as $order) {

			$payments = $order->Payments();
			if ($payments && $payments->exists()) foreach ($payments as $payment) {

				if ($payment->XeroPaymentID) {
					continue;
				}

				$data[$i]['Payment'] = array(
					'Invoice' => array(
						'InvoiceID' => $order->XeroInvoiceID
					),
					'Account' => array(
						'Code' => $defaultAccountPurchasesCode
					),
					'Date' => date('Y-m-d', strtotime($payment->Created)),
					'Amount' => $payment->Amount->getAmount(),
					'Reference' => $invoicePrefix . $payment->ID
				);

				$i++;
			}
		}

		// If no data do not send to Xero
		if (empty($data)) {
			return;
		}

		$paymentsXML = new SimpleXMLElement("<Payments></Payments>");
		$this->arrayToXML($data, $paymentsXML);
		$xml = $paymentsXML->asXML();

		$response = $xeroConnection->request('POST', $xeroConnection->url('Payments', 'core'), array(), $xml);

		if ($xeroConnection->response['code'] == 200) {

			$payments = $xeroConnection->parseResponse($xeroConnection->response['response'], $xeroConnection->response['format']);
			echo count($payments->Payments[0]). " payments(s) created in this Xero organisation.";

			// Update payments that are sent to Xero so that they are not sent again
			foreach ($payments->Payments->Payment as $remittance) {

				$payment = Payment::get()
					->filter('ID', str_replace($invoicePrefix, '', $remittance->Reference->__toString()))
					->first();

				if ($payment && $payment->exists()) {
					$payment->XeroPaymentID = $remittance->Reference->__toString();
					$payment->write();
				}
			}
		}
		else {
			echo 'Error: ' . $xeroConnection->response['response'] . PHP_EOL;
			SS_Log::log(new Exception(print_r($xeroConnection, true)), SS_Log::NOTICE);
		}
	}

	private function arrayToXML($data, &$xml) {

		foreach($data as $key => $value) {

			if(is_array($value)) {
				if(!is_numeric($key)){
					$subnode = $xml->addChild("$key");
					$this->arrayToXML($value, $subnode);
				}
				else{
					$this->arrayToXML($value, $xml);
				}
			}
			else {
				$xml->addChild("$key", "$value");
			}
		}
	}

	private function prettyPrintXML($xml) {

		$domxml = new DOMDocument('1.0');
		$domxml->preserveWhiteSpace = false;
		$domxml->formatOutput = true;
		$domxml->loadXML($xml);
		return $domxml->saveXML();
	}
}
