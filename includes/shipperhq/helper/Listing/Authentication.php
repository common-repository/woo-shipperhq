<?php


/*
 * Shipper HQ
 *
 * @category ShipperHQ
 * @package woocommerce-shipperhq
 * @copyright Copyright (c) 2020 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */


class ShipperHQ_Listing_Authentication_Helper
{
    const DEFAULT_AUTH_ENDPOINT = "https://shipperhq.com/oauth/ec/token/";
    const DEFAULT_AUTH_TIMEOUT = 30;

    /** @var string */
    private $endpoint;
    /** @var string */
    private $api_key;
    /** @var string */
    private $auth_code;
    /** @var string */
    private $auth_token = false;
    /** @var string */
    private $token_expiration;
    /** @var string */
    private $logger;
    /** @var mixed */
    private $settings;

    /** @var ShipperHQ_Listing_Authentication_Helper */
    private static $instance;

    /**
     * ShipperHQ_Listing_Authentication_Helper constructor.
     * @param WC_Logger $logger
     */
    private function __construct($logger)
    {
        $this->logger = $logger;
        $this->settings = get_option( 'woocommerce_shipperhq_settings' );
    }

    /**
     * Singleton
     *
     * @param WC_Logger $logger
     * @return ShipperHQ_Listing_Authentication_Helper
     */
    public static function getInstance($logger)
    {
        if (is_null(self::$instance)) {
            self::$instance = new self($logger);
        }
        return self::$instance;
    }


    /**
     * @return string|false
     */
    public function getAuthToken()
    {
        // get cached auth token
        if (!$this->auth_token) {
            $this->auth_token = $this->settings['auth_token'];
        }

        if (!$this->auth_token || $this->tokenExpiresSoon()) {
            if (!$this->hasValidConfig()) {
                $this->logger->add('ShipperHQ', 'Cannot fetch Auth Token, config is invalid.  Ensure API Key and Auth Code are set in SHQ settings');
                return false;
            }

            $fullUrlString = $this->getFullUrl();

            $response = wp_remote_get($fullUrlString, [ 'timeout' => self::DEFAULT_AUTH_TIMEOUT ]);
            $body  = wp_remote_retrieve_body($response);

            $this->handleResponse($body);
        }

        return $this->auth_token;
    }

    /**
     * @return string
     */
    public function getTokenExpiration()
    {
        if (empty($this->token_expiration)) {
            $this->token_expiration = $this->settings['auth_token_expiration'];
        }
        return $this->token_expiration;
    }

    private function tokenExpiresSoon() {
        $currentTime = current_time('timestamp', true);
        $expiresAt = $this->getTokenExpiration();
        $oneHour = 60/*minutes*/ * 60/*seconds*/;

        return empty($expiresAt)
            || !is_integer($expiresAt)
            || ($expiresAt - $currentTime) < $oneHour;
    }

    /**
     * @return string
     */
    private function getApiKey()
    {
        if (empty($this->api_key)) {
            $this->api_key = $this->settings['api_key'];
        }
        return $this->api_key;
    }

    /**
     * @return string
     */
    private function getAuthCode()
    {
        if (empty($this->auth_code)) {
            $this->auth_code = $this->settings['authentication_code'];
        }
        return $this->auth_code;
    }

    /**
     * @return string
     */
    private function getEndpoint()
    {
        $endpoint = $this->endpoint; // TODO: Expand this
        return $endpoint ? $endpoint : self::DEFAULT_AUTH_ENDPOINT;
    }

    /**
     * @param $var
     * @return bool
     */
    private function is_nonempty_string($var)
    {
        return is_string($var) && !empty($var);
    }

    /**
     * @return bool
     */
    private function hasValidConfig()
    {
        return $this->is_nonempty_string($this->getApiKey())
            && $this->is_nonempty_string($this->getAuthCode())
            && $this->is_nonempty_string($this->getEndpoint());
    }

    /**
     * @return string
     */
    private function getFullUrl() {
        $url = $this->getEndpoint();

        $params = [
            'client_id' =>  $this->getApiKey(),
            'client_secret' => $this->getAuthCode(),
            'grant_type' => 'client_credentials'
        ];

        $queryString = http_build_query($params);

        return "$url?$queryString";
    }

    /**
     * @param $body
     * @return false|string
     */
    private function handleResponse($body)
    {
        $parsedBody = json_decode($body, true);

        if (isset($parsedBody['token'])) {
            $token = $this->handleSuccessfulResponse($parsedBody);
            if ($token === false) {
                $this->logger->add('ShipperHQ', 'Fetched an Auth Token but the response was invalid');
            }

            return $token;
        } elseif (isset($parsedBody['error'])) {
            return $this->handleResponseErrors($parsedBody);
        } else {
            return $this->handleInvalidResponse($body);
        }
    }

    /**
     * @param $parsedBody
     * @return false
     */
    private function handleResponseErrors($parsedBody)
    {
        $this->logger->add('ShipperHQ', "Failed to fetch Auth token.  Error returned: '${parsedBody['error']}'");
        return false;
    }

    /**
     * @param $body
     * @return false
     */
    private function handleInvalidResponse($body)
    {
        $this->logger->add('ShipperHQ', 'Failed to fetch Auth token.  Unexpected response.');
        $this->logger->add('ShipperHQ', print_r($body, true));
        return false;
    }

    /**
     * @param $parsedBody
     * @return string|false
     */
    private function handleSuccessfulResponse($parsedBody)
    {

        $tokenStr = $parsedBody['token'];

        $token = (new \Lcobucci\JWT\Parser())->parse($tokenStr);
        $verified = $token->verify(new \Lcobucci\JWT\Signer\Hmac\Sha256(), $this->getAuthCode());
        $currentTime = current_time('timestamp', true);
        $issuedAt = $token->getClaim('iat');
        $expiresAt = $token->getClaim('exp');

        if ($verified && $issuedAt <= $currentTime && $currentTime <= $expiresAt) {
            $this->settings['auth_token_expiration'] = $this->token_expiration = $expiresAt;
            $this->settings['auth_token'] = $this->auth_token = $tokenStr;

            if (!update_option('woocommerce_shipperhq_settings', $this->settings)) {
                $this->logger->add("ShipperHQ", "Failed to store auth token and expiration");
            }

            return $tokenStr;
        }

        return false;
    }
}
