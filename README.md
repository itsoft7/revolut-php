# revolut-php
One file. No dependencies for security reasons.

Revolut API has next bugs:

1. scope does not work. When I ask READ I also can WRITE and send payments. It's shame.

2. No possibility to revoke token. Again it's shame. You can ask for new token and not to save it, but it is crutch.

3. No possibility to add account to counterparty. Delete and create again is bad solution due to old payments linked to old id of counterparty.
So we need add and delete accounts to counterparty.

4. It's really very bad that when I send payment via API no double authentication. I need second independed channel to confirm (sign) payments.
It can be SMS or ENUM.

Revolut API is very unsafe. 

[Revolut Business API Documentation](https://developer.revolut.com/docs/manage-accounts/#introduction-to-the-business-api).

## Installation

```bash
composer require itsoft/revolut
```

## Usage

```bash
cd your_secret_keys_dir/revolut
openssl genrsa -out privatekey.pem 1024
openssl req -new -x509 -key privatekey.pem -out publickey.cer -days 1825
more publickey.cer
```

Add publickey.cer content to [Revolut Business API settings page](https://business.revolut.com/settings/api) and point there your OAuth redirect URI.
Save ClientId and Enable API access to your account.

### Create revolut.cfg.php
This config saves tokens in files. You can save them to database or other places, but for security reasons better not to save them, see the next section Secured revolut.cfg.php. 

```
<?php
$ROOT_PATH = '/www/your-crm...';

require_once $ROOT_PATH . 'vendor/autoload.php';
$path2token = $ROOT_PATH . 'token/revolut.txt';
$path2refresh_token = $ROOT_PATH . 'token/revolut_refresh_token.txt';


$a_token = json_decode(file_get_contents($path2token));
$r_token = json_decode(file_get_contents($path2refresh_token));

$params = [
	'scope' => 'READ', //WRITE or both
	'api_url' => 'https://b2b.revolut.com/api/1.0', 
	'client_id' => 'aaasssss....',
	'private_key' => file_get_contents('your_secret_keys_dir/revolut/privatekey.pem'),
	'redirect_uri' => 'https://your_site.com/redirect_uri/', //OAuth redirect URI
	'auth_url' => 'https://business.revolut.com/app-confirm', 
	'access_token' => $a_token->access_token,
	'access_token_expires' => $a_token->expires,
	'refresh_token' => $r_token->refresh_token,
	'refresh_token_expires' => $r_token->expires,
	'save_access_token' => function ($access_token, $expires) use ($path2token) {file_put_contents($path2token, json_encode(['access_token' => $access_token, 'expires' => $expires]));},
	'save_refresh_token' => function ($refresh_token, $expires) use ($path2refresh_token) {file_put_contents($path2refresh_token, json_encode(['refresh_token' => $refresh_token, 'expires' => $expires]));},
	'log_error' => function ($error){mail('your_email@domin.com', 'Revolut API Error', $error);}
];


//for debug you can use
// $params['api_url'] =  'https://sandbox-b2b.revolut.com/api/1.0';


$revolut = new \ITSOFT\Revolut\Revolut($params);
```

