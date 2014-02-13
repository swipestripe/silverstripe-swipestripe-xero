# SwipeStripe Xero

## Maintainer Contact
SwipeStripe  
[Contact Us](http://swipestripe.com/support/contact-us)

## Requirements
* SilverStripe 3.1.*
* SwipeStripe 2.1.*
* SwipeStripe Addresses 2.1.*

## Documentation
Invoices and Payments are created in Xero for completed orders using a build task, they are not sent to Xero more than once. The task is availble via /dev/tasks but potentially this build task could be run on a cron job. Xero tax types and rates can be applied to order items and modifications easily depending on the tax rules for each project using dependency injection.

Documentation for [getting started with Xero](http://developer.xero.com/documentation/getting-started/getting-started-guide/).

## Installation Instructions

### Composer
1. composer require swipestripe/swipestripe-xero:dev-master

### Manual
1. Place this directory in the root of your SilverStripe installation, rename the folder 'swipestripe-xero'.
2. Visit yoursite.com/dev/build?flush=all to rebuild the database.

### Configuration
This module requires quite a lot of configuration (and testing) for each install. Tax rules for each shop will likely vary and so these must be set up so that invoices in Xero match orders correctly.

#### API
You will need to provide the public/private key pair for your Xero account as well as the consumer and shared secret keys. Configuration is set in xero.xml but should be overridden in your mysite/ YAML configuration files e.g:

```yaml
	---
	Name: mysite_swipestripe_xero
	After: '#swipestripe_xero'
	---
	CreateInvoicesTask:
	  appType: 'Private'
	  oauthCallback: 'oob'
	  userAgent: 'SwipeStripe Demo Site'
	  consumerKey: 'SOME KEY HERE'
	  sharedSecret: 'SECRET KEY HERE'
	  privateKeyPath: '/some/path/privatekey.pem'
	  publicKeyPath: '/some/path/publickey.cer'
	  invoicePrefix: 'WEB-'
	  defaultAccountCode: '200'
	  defaultAccountPurchasesCode: '090'
```

Many of these settings relate to Xero such as the default account codes, app type, callback etc.

#### Tax Settings
Tax rates are applyed to each line item in a Xero invoice, different shops will likely have different tax requirements so the tax rates that are applied need to be defined. 

__Note:__  
This module has a built in tax module so it will not work with other tax modules such as [Flat Fee Tax](http://swipestripe.com/products/extensions/tax/).

To set up tax rates the first step is to subclass XeroTaxCalculator (e.g: XeroTaxCalculator_NZ). This is where you can decide on how tax rates are applied to each item and modification in the order. Then you need to set your new tax calculator as the dependency for XeroTaxModification in your YAML configuration file e.g:

```yaml
	---
	Name: mysite_swipestripe_xero
	After: '#swipestripe_xero'
	---
	XeroTaxModification:
	  dependencies:
	    taxCalculator: %$XeroTaxCalculator_NZ
```

## Usage Overview
1. Run the build task to send orders to Xero (this could potentially be set up to run on a cron job)

Very good idea to set up with a Xero Developer account for testing.

## Attribution
Thanks to:

* [Xero OAuth](https://github.com/XeroAPI/XeroOAuth-PHP)

## License
	Copyright (c) 2014, Frank Mullenger
	All rights reserved.

	Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

			* Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
			* Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the 
				documentation and/or other materials provided with the distribution.
			* Neither the name of SilverStripe nor the names of its contributors may be used to endorse or promote products derived from this software 
				without specific prior written permission.

	THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE 
	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE 
	LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE 
	GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, 
	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY 
	OF SUCH DAMAGE.
