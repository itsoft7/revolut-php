# revolut-php
simple PHP library for the [Revolut Business API](https://developer.revolut.com/docs/manage-accounts/#get-started)

[![Build](https://github.com/itsoft7/revolut-php/actions/workflows/php.yml/badge.svg)](https://github.com/itsoft7/revolut-php/actions/workflows/php.yml)

## :rocket: Features
* One file
* No external dependencies
* Support for older versions of PHP

## :bulb: Motivation
Revolut API has the following bugs:

* Scope is not supported. It's a shame. E.g. you cannot give read-only access to your Revolut application.
* No possibility to revoke the token. Again it's a shame. You can ask for a new token and not save it, but it's a kludge.
* No possibility to add a second account (IBAN) to a counterparty. Delete and create is a bad solution due to old payments are linked to the counterparty uuid. We can add several counterparties with different IBANs, and get into a mess while distinguishing between 2 different companies with the same name.
* It's very bad there's no MFA when sending payments via the API. We need a second independent channel to confirm (sign) payments. It can be SMS or TOTP.

Revolut API is very unsafe.

[Revolut Business API Documentation](https://developer.revolut.com/docs/manage-accounts/#introduction-to-the-business-api).

## :lollipop: Demo
You can find a demo app using this library here: https://github.com/maslick/revolutor

## :white_check_mark: Installation
```bash
composer require itsoft/revolut
```

## :8ball: Usage

```bash
cd your_secret_keys_dir/revolut
openssl genrsa -out privatekey.pem 1024
openssl req -new -x509 -key privatekey.pem -out publickey.cer -days 1825
more publickey.cer
```

Add the contents of publickey.cer to [Revolut Business API settings page](https://business.revolut.com/settings/api) and point your OAuth redirect URI.
Save ClientId and Enable API access to your account.

### Create insecure revolut.cfg.php
This config saves tokens in files. You can save them to a database or other places, but for security reasons better not to save them, see the next section Secured revolut.cfg.php.

```
<?php
$ROOT_PATH = '/www/your-crm...';

require_once $ROOT_PATH . 'vendor/autoload.php';
$path2token = $ROOT_PATH . 'token/revolut.txt';
$path2refresh_token = $ROOT_PATH . 'token/revolut_refresh_token.txt';


$a_token = json_decode(file_get_contents($path2token));
$r_token = json_decode(file_get_contents($path2refresh_token));

$params = [
	'apiUrl' => 'https://b2b.revolut.com/api/1.0',
	'authUrl' => 'https://business.revolut.com/app-confirm', 
	'clientId' => 'YOUR-CLIENT-ID',
	'privateKey' => file_get_contents('your_secret_keys_dir/revolut/privatekey.pem'),
	'redirectUri' => 'https://your_site.com/redirect_uri/', //OAuth redirect URI
	'accessToken' => $a_token->access_token,
	'accessTokenExpires' => $a_token->expires,
	'refreshToken' => $r_token->refresh_token,
	'refreshTokenExpires' => $r_token->expires,
	'saveAccessTokenCb' => function ($access_token, $expires) use ($path2token) {file_put_contents($path2token, json_encode(['access_token' => $access_token, 'expires' => $expires]));},
	'saveRefreshTokenCb' => function ($refresh_token, $expires) use ($path2refresh_token) {file_put_contents($path2refresh_token, json_encode(['refresh_token' => $refresh_token, 'expires' => $expires]));},
	'logError' => function ($error){mail('your_email@domin.com', 'Revolut API Error', $error);}
];


// for debug you can use
// $params['apiUrl'] =  'https://sandbox-b2b.revolut.com/api/1.0';
// $params['authUrl'] = 'https://sandbox-business.revolut.com/app-confirm';

$revolut = new \ITSOFT\Revolut\Revolut($params);
```

### Secured revolut.cfg.php

```
<?php
$ROOT_PATH = '/www/your-crm...';

require_once $ROOT_PATH . 'vendor/autoload.php';
$params = [
	'apiUrl' => 'https://b2b.revolut.com/api/1.0',
	'authUrl' => 'https://business.revolut.com/app-confirm',
	'clientId' => 'YOUR-CLIENT-ID',
	'privateKey' => file_get_contents('your_secret_keys_dir/revolut/privatekey.pem'),
	'redirectUri' => 'https://your_site.com/redirect_uri/', //OAuth redirect URI
	'accessToken' => '',
	'accessTokenExpires' => '',
	'refreshToken' => '',
	'refreshTokenExpires' => '',
	'saveAccessTokenCb' => function ($access_token, $expires){},
	'saveRefreshTokenCb' => function ($refresh_token, $expires){},
	'logError' => function ($error){mail('your_email@domin.com', 'Revolut API Error', $error);}
];


// for debug you can use
// $params['apiUrl'] =  'https://sandbox-b2b.revolut.com/api/1.0';
// $params['authUrl'] = 'https://sandbox-business.revolut.com/app-confirm';


$revolut = new \ITSOFT\Revolut\Revolut($params);
```


### Code for OAuth redirect URI (https://your_site.com/redirect_uri/)
```
require_once 'revolut.cfg.php';

if(isset($_GET['code'])) $revolut->exchangeCodeForAccessToken();
elseif(!$revolut->accessToken) $revolut->goToConfirmationURL();
  
print "<pre><h2>accounts</h2>\n";
print_r($revolut->accounts());

print "<h2>counterparties</h2>\n";
print_r($revolut->counterparties());

print "<h2>getExchangeRate</h2>\n";
print_r($revolut->getExchangeRate(['from'=>'USD', 'to'=>'EUR']));
print_r($revolut->getExchangeRate(['from'=>'EUR', 'to'=>'USD']));

print "<h2>transactions</h2>\n";
print_r($revolut->transactions());
```

### Create payment and other methods

```
$revolut->createPayment($params); 
```
For $params see [tutorials-make-a-payment-create-payment](https://developer.revolut.com/docs/manage-accounts/#tutorials-tutorials-make-a-payment-create-payment) and [api-reference createPayment](https://developer.revolut.com/api-reference/business/#operation/createPayment).

For other methods see https://github.com/itsoft7/revolut-php/blob/master/src/Revolut.php
