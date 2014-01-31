<?php

require BASE_PATH . '/swipestripe-xero/thirdparty/XeroOAuth.php';

class RemoveDevOrdersTask extends BuildTask {
	
	protected $title = 'Create Xero Invoices';
	
	protected $description = 'Create invoices in Xero for SwipeStripe orders.';

	function run($request) {


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
				
				$this->createInvoice($XeroOAuth);
				// include 'tests/tests.php';
			}
			
			// testLinks ();
		}
	}

	private function createInvoice($XeroOAuth) {
		$xml = "<Invoices>
							<Invoice>
								<Type>ACCREC</Type>
								<Contact>
									<Name>Martin Hudson</Name>
								</Contact>
								<Date>2013-05-13T00:00:00</Date>
								<DueDate>2013-05-20T00:00:00</DueDate>
								<LineAmountTypes>Exclusive</LineAmountTypes>
								<LineItems>
									<LineItem>
										<Description>Monthly rental for property at 56a Wilkins Avenue</Description>
										<Quantity>4.3400</Quantity>
										<UnitAmount>395.00</UnitAmount>
										<AccountCode>200</AccountCode>
									</LineItem>
							 </LineItems>
						 </Invoice>
					 </Invoices>";
		$response = $XeroOAuth->request('POST', $XeroOAuth->url('Invoices', 'core'), array(), $xml);
		if ($XeroOAuth->response['code'] == 200) {
				$invoice = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
				echo "" . count($invoice->Invoices[0]). " invoice created in this Xero organisation.";
				if (count($invoice->Invoices[0])>0) {

					SS_Log::log(new Exception(print_r($invoice->Invoices[0]->Invoice, true)), SS_Log::NOTICE);
					// echo "The first one is: </br>";
					// pr($invoice->Invoices[0]->Invoice);
					// outputError($XeroOAuth);
				}
		} else {
			SS_Log::log(new Exception(print_r($XeroOAuth, true)), SS_Log::NOTICE);
			// outputError($XeroOAuth);
		}
	}
}