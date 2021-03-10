<?php
/**
 * Revolut PHP SDK
 *
 * @author  Igor Tarasov <igor@itsoft.ru>
 * @author  Pavel Maslov <pavel.masloff@gmail.com>
 * @license https://github.com/itsoft7/revolut-php/blob/master/LICENSE MIT
 */
namespace ITSOFT\Revolut;

class Revolut
{

    /**
     * Redirect url
     *
     * @var string
     */
    private $redirectUri;

    /**
     * Revolut client id
     *
     * @var string
     */
    private $clientId;

    /**
     * Revolut private key
     *
     * @var string
     */
    private $privateKey;

    /**
     * Revolut access token
     *
     * @var string
     */
    public $accessToken;

    /**
     * Epoch time when access token expires
     *
     * @var numeric
     */
    private $accessTokenExpires;

    /**
     * Revolut refresh token
     *
     * @var string
     */
    private $refreshToken;

    /**
     * Epoch time when refresh token expires
     *
     * @var string
     */
    private $refreshTokenExpires;

    /**
     * Revolut API base URL (e.g. https://b2b.revolut.com/api/1.0)
     *
     * @var string
     */
    private $apiUrl;

    /**
     * In case of an error - redirect to this URL
     *
     * @var string
     */
    private $errorUrl = "/error.php";

    /**
     * Callback function has input 2 parameters - $access_token and $expires
     *
     * @var callable
     */
    private $saveAccessTokenCb;

    /**
     * Callback function has input 2 parameters - $access_token and $expires
     *
     * @var callable
     */
    private $saveRefreshTokenCb;

    /**
     * Constructor
     *
     * @param array $params input parameters
     */
    public function __construct(array $params)
    {
        foreach ($params as $k => $v) {
            $this->$k = $v;
        }
    }

    /**
     * Base64 encode
     *
     * @param string $data input string
     *
     * @return string
     */
    public static function base64urlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Generate a client-assertion JWT that you use to exchange your authorization code for access token.
     * The client-assertion JWT should be signed with your private key.
     *
     * @return string
     */
    public function getJWT()
    {
        $header  = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];
        $payload = [
            'iss' => parse_url($this->redirectUri, PHP_URL_HOST),
            'sub' => $this->clientId,
            'aud' => 'https://revolut.com',
            'exp' => (time() + 40 * 60),
        ];

        $segments   = [];
        $segments[] = static::base64urlEncode(json_encode($header));
        $segments[] = static::base64urlEncode(json_encode($payload));
        $signingStr = implode('.', $segments);

        $signature = '';
        $success   = openssl_sign($signingStr, $signature, $this->privateKey, 'SHA256');
        if ($success === false) {
            error_log("openssl_sign returns FALSE.\n");
            exit;
        }

        $segments[] = static::base64urlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * Make a HTTP request
     *
     * @param string $url     URL
     * @param string $method  HTTP method
     * @param string $params  parameters
     * @param array  $headers headers
     *
     * @return string
     */
    public function curl($url, $method = 'get', $params = '', $headers = [])
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        // The maximum number of seconds to allow cURL functions to execute.
        curl_setopt($ch, CURLOPT_TIMEOUT, 200);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($method === 'post') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        } else if ($method === 'delete') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $lastError = curl_error($ch);
            if (isset($this->logError) === true) {
                $logError = $this->logError;
                $logError($lastError);
            }

            return $lastError;
        }

        curl_close($ch);
        return $response;
    }

    /**
     * Call Revolut API
     *
     * @param string $relativePath relative path
     * @param string $method       HTTP method
     * @param string $params       parameters
     * @param array  $extraHeaders headers
     *
     * @return string
     */
    public function api($relativePath, $method = 'get', $params = '', $extraHeaders = [])
    {
        if (strlen($this->accessToken) === 0) {
            error_log("No token available");
            $this->goToLocation($this->errorUrl);
        } else if (time() > ($this->accessTokenExpires - 30)) {
            error_log("Token has expired");
            $this->refreshAccessToken();
        }

        $url = $this->apiUrl.$relativePath;

        if ($method === 'get') {
            if ($params === true) {
                $url .= '?'.http_build_query($params);
            }
        } else {
            $params = json_encode($params);
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer '.$this->accessToken,
        ];
        $headers = array_merge($headers, $extraHeaders);
        $data    = $this->curl($url, $method, $params, $headers);
        $data    = json_decode($data);
        return $data;
    }

    /**
     * Go to a location
     *
     * @param $location string URL e.g. http://localhost:8080/hello.php
     *
     * @return string
     */
    public function goToLocation($location)
    {
        header('Location: '.$location);
        exit;
    }

    /**
     * Exchange authorization code for access token
     *
     * @return void
     */
    public function exchangeCodeForAccessToken()
    {
        error_log("Exchanging code for access token...");
        $params = [
            'grant_type'            => 'authorization_code',
            'code'                  => $_GET['code'],
            'client_id'             => $this->clientId,
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion'      => $this->getJWT(),
        ];

        $url     = $this->apiUrl.'/auth/token';
        $params  = http_build_query($params);
        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        $data    = $this->curl($url, 'post', $params, $headers);
        $data    = json_decode($data);

        if (isset($data->access_token) === true && strlen($data->access_token) > 0) {
            $this->accessToken         = $data->access_token;
            $this->refreshToken        = $data->refresh_token;
            $this->accessTokenExpires  = (time() + $data->expires_in);
            $this->refreshTokenExpires = (time() + 90 * 24 * 60 * 60);

            $saveAccessTokenCb = $this->saveAccessTokenCb;
            $saveAccessTokenCb($this->accessToken, $this->accessTokenExpires);
            $saveRefreshTokenCb = $this->saveRefreshTokenCb;
            $saveRefreshTokenCb($this->refreshToken, $this->refreshTokenExpires);
        } else {
            error_log(print_r($data, true));
        }
    }

    /**
     * After the access token expires, use the refresh_token to request a new one.
     *
     * @return void
     */
    public function refreshAccessToken()
    {
        error_log("Refreshing access token...");
        if (time() > ($this->refreshTokenExpires - 30)) {
            error_log("Refresh token has expired");
            $this->goToLocation($this->errorUrl);
        }

        $params = [
            'grant_type'            => 'refresh_token',
            'refresh_token'         => $this->refreshToken,
            'client_id'             => $this->clientId,
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion'      => $this->getJWT(),
        ];

        $url     = $this->apiUrl.'/auth/token';
        $params  = http_build_query($params);
        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        $data    = $this->curl($url, 'post', $params, $headers);
        $data    = json_decode($data);
        $this->accessToken        = $data->access_token;
        $this->accessTokenExpires = (time() + $data->expires_in);

        $saveAccessTokenCb = $this->saveAccessTokenCb;
        $saveAccessTokenCb($this->accessToken, $this->accessTokenExpires);
    }

    /**
     * Get accounts
     *
     * @return string
     */
    public function accounts()
    {
        return $this->api('/accounts');
    }

    /**
     * Get account
     *
     * @param string $id account id
     *
     * @return string
     */
    public function account($id)
    {
        return $this->api('/accounts/'.$id);
    }

    /**
     * Get account details
     *
     * @param string $id account id
     *
     * @return string
     */
    public function accountDetails($id)
    {
        return $this->api('/accounts/'.$id.'/bank-details');
    }

    /**
     * Add a counterparty
     *
     * @param object $params parameters
     *
     * @return mixed
     */
    public function addCounterparty($params)
    {
        return $this->api('/counterparty', 'post', $params);
    }

    /**
     * Delete a counterparty
     *
     * @param string $counterparty counterparty id
     *
     * @return mixed
     */
    public function deleteCounterparty($counterparty)
    {
        return $this->api('/counterparty/'.$counterparty, 'delete');
    }

    /**
     * Get a counterparty
     *
     * @param string $counterparty counterparty id
     *
     * @return mixed
     */
    public function getCounterparty($counterparty)
    {
        return $this->api('/counterparty/'.$counterparty);
    }

    /**
     * Get all counterparties
     *
     * @return mixed
     */
    public function counterparties()
    {
        return $this->api('/counterparties');
    }

    /**
     * Create a payment
     *
     * @param object $params parameters
     *
     * @return mixed
     */
    public function createPayment($params)
    {
        return $this->api('/pay', 'post', $params);
    }

    /**
     * Get transactions
     *
     * @param object $params parameters
     *
     * @return mixed
     */
    public function transactions($params = null)
    {
        return $this->api('/transactions', 'get', $params);
    }

    /**
     * Get a transaction
     *
     * @param string $id transaction id
     *
     * @return mixed
     */
    public function transaction($id)
    {
        return $this->api('/transaction/'.$id);
    }

    /**
     * Create a transfer
     *
     * @param object $params parameters
     *
     * @return mixed
     */
    public function createTransfer($params)
    {
        return $this->api('/transfer', 'post', $params);
    }

    /**
     * Create a payment draft
     *
     * @param object $params parameters
     *
     * @return mixed
     */
    public function createPaymentDraft($params)
    {
        return $this->api('/payment-drafts', 'post', $params);
    }

    /**
     * Delete a payment draft
     *
     * @param string $id payment draft id
     *
     * @return mixed
     */
    public function deletePaymentDraft($id)
    {
        return $this->api('/payment-drafts/'.$id, 'delete');
    }

    /**
     * Get all payment drafts
     *
     * @return mixed
     */
    public function paymentDrafts()
    {
        return $this->api('/payment-drafts');
    }

    /**
     * Get a payment draft
     *
     * @param string $id payment draft id
     *
     * @return mixed
     */
    public function paymentDraft($id)
    {
        return $this->api('/payment-drafts/'.$id);
    }

    /**
     * Get exchange rate
     *
     * @param array $params parameters
     *
     * @return mixed
     */
    public function getExchangeRate($params)
    {
        return $this->api('/rate', 'get', $params);
    }

    /**
     * Exchange money
     *
     * @param object $params parameters
     *
     * @return mixed
     */
    public function exchangeMoney($params)
    {
        return $this->api('/exchange', 'post', $params);
    }
}
