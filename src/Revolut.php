<?php

namespace ITSOFT\Revolut;

class Revolut
{
    
public function __construct(array $params)
{
 foreach($params as $k=>$v)
  $this->$k = $v;
}
 
public static function base64url_encode($data){return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');}
	
public function getJWT()
{
	$header = ['alg' => 'RS256', 'typ' => 'JWT'];
	$payload = [
		'iss' => parse_url($this->redirect_uri, PHP_URL_HOST),
		'sub' => $this->client_id,
		'aud' => 'https://revolut.com',
		'exp' => time() + 60*60
	];

	$segments = [];
	$segments[] = static::base64url_encode(json_encode($header));
	$segments[] = static::base64url_encode(json_encode($payload));
	$signing_str = implode('.', $segments);

	$signature = '';
	$success = openssl_sign($signing_str, $signature, $this->private_key, 'SHA256');
	if(!$success)
	{
		error_log("openssl_sign returns FALSE.\n");
		exit;
	} 

	$segments[] = static::base64url_encode($signature);
		
return implode('.', $segments);
}
 
public function curl($url, $method='get', $params='', $headers=[])
{	
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10 );
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE); 
	curl_setopt($ch, CURLOPT_TIMEOUT, 200); //The maximum number of seconds to allow cURL functions to execute.
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	if($method=='post')
	{
	 curl_setopt($ch, CURLOPT_POST, 1 );
	 curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
	}
	elseif($method=='delete')
	 curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
	
	$response = curl_exec($ch);
	if($response==FALSE) 
	{
	  $lastError = curl_error($ch);
	  if(isset($this->log_error))
	  {
	   $log_error = $this->log_error;
	   $log_error($lastError);
	  }
	   
	  return $lastError;	
	}
	  
	curl_close($ch);
	return $response;
}

public function api($relative_path, $method='get', $params='', $extra_headers=[])
{
	if(!$this->access_token)
	 $this->authLocation();
	elseif(time()>$this->access_token_expires-30)
	 $this->refreshAccessToken();

	$url = $this->api_url . $relative_path;
	
	if($method=='get')
	{
		if($params)
		 $url .= '?' . http_build_query($params);
	}
	else
	 $params = json_encode($params);
	 
	$headers    = array(
	'Content-Type: application/json',
	'Accept: application/json',
	'Authorization: Bearer ' . $this->access_token,
	);
	$headers = array_merge($headers, $extra_headers);
	$data = $this->curl($url, $method, $params, $headers);
	$data = json_decode($data);
	return $data;
}

public function authUri()
{
	$params = [
		'client_id' => $this->client_id,
		'response_type' => 'code',
		'redirect_uri' => $this->redirect_uri,
		'scope' => $this->scope
	];
	return 'https://business.revolut.com/app-confirm?' . http_build_query($params);
}

public function authLocation()
{
    $uri = $this->authUri();
    header('Location: ' . $uri);
    exit;
}

public function exchangeCodeForAccessToken()
{
    $params = [
		'grant_type' => 'authorization_code',
		'code' => $_GET['code'],
		'client_id' => $this->client_id,
		'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
		'client_assertion' => $this->getJWT()
	];
	
	$url = $this->api_url . '/auth/token';
	$params = http_build_query($params);
	$headers = ['Content-Type: application/x-www-form-urlencoded'];
	$data = $this->curl($url, 'post', $params, $headers);
	$data = json_decode($data);
	
	if($data->access_token)
	{
		$this->access_token = $data->access_token;
		$this->refresh_token = $data->refresh_token;
		$this->access_token_expires = time()+$data->expires_in;
		$this->refresh_token_expires = time()+90*24*60*60;	

		$save_access_token = $this->save_access_token;
		$save_access_token($this->access_token, $this->access_token_expires);
		$save_refresh_token = $this->save_refresh_token;
		$save_refresh_token($this->refresh_token, $this->refresh_token_expires);
		header('Location: ' . $this->redirect_uri);
		exit;
	}
	else
	{
		error_log(print_r($data, true));
		exit;
	}
}

public function refreshAccessToken()
{
	if(time()>$this->refresh_token_expires-30)
	 $this->authLocation();
	 
    $params = [
		'grant_type' => 'refresh_token',
		'refresh_token' => $this->refresh_token,
		'client_id' => $this->client_id,
		'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
		'client_assertion' => $this->getJWT()
	];
	
	$url = $this->api_url . '/auth/token';
	$params = http_build_query($params);
	$headers = ['Content-Type: application/x-www-form-urlencoded'];
	$data = $this->curl($url, 'post', $params, $header);
	$data = json_decode($data);
	$this->access_token = $data->access_token;
	$this->access_token_expires = time()+$data->expires_in;

	$save_access_token = $this->save_access_token;
	$save_access_token($this->access_token, $this->access_token_expires);
}

public function accounts()
{
	return $this->api('/accounts');
}

public function account($id)
{
	return $this->api('/accounts/' . $id);
}

public function accountDetails($id)
{
	return $this->api('/accounts/' . $id . '/bank-details');
}

/**
 * If in $params is address, all address fields must be.
 */
public function addCounterparty($params)
{
	return $this->api('/counterparty', 'post', $params);
}

public function deleteCounterparty($counterparty)
{
	return $this->api('/counterparty/' . $counterparty, 'delete');
}

public function getCounterparty($counterparty)
{
	return $this->api('/counterparty/' . $counterparty);
}

public function counterparties()
{
	return $this->api('/counterparties');
}

public function createPayment($params)
{
	return $this->api('/pay', 'post', $params);
}

public function transactions($params)
{
	return $this->api('/transactions', 'get', $params);
}

public function transaction($id)
{
	return $this->api('/transaction/' . $id);
}

public function createTransfer($params)
{
	return $this->api('/transfer', 'post', $params);
}

public function createPaymentDraft($params)
{
	return $this->api('/payment-drafts', 'post', $params);
}

public function deletePaymentDraft($id)
{
	return $this->api('/payment-drafts/' . $id, 'delete');
}

public function paymentDrafts()
{
	return $this->api('/payment-drafts');
}

public function paymentDraft($id)
{
	return $this->api('/payment-drafts/' .  $id);
}


public function getExchangeRate($params)
{
	return $this->api('/rate', 'get', $params);
}

public function exchangeMoney($params)
{
	return $this->api('/exchange', 'post', $params);
}

}
