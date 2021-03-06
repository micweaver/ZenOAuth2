<?php
namespace ZenOAuth2;

abstract class OAuth2Abstract {
	/**
	 * @ignore
	 */
	public $client_id;
	/**
	 * @ignore
	 */
	public $client_secret;
	/**
	 * Contains the last API call.
	 *
	 * @ignore
	 */
	public $url;
	
	protected $_curlOptions = array(
		CURLOPT_HTTP_VERSION	=> CURL_HTTP_VERSION_1_0,
		CURLOPT_USERAGENT		=> 'ZenOAuth2 v0.3',
		CURLOPT_CONNECTTIMEOUT	=> 30,
		CURLOPT_TIMEOUT			=> 30,
		CURLOPT_SSL_VERIFYPEER	=> FALSE,
	);
	
	/**
	 * Contains the last HTTP headers returned.
	 *
	 * @ignore
	 */
	public $http_info;

	/**
	 * print the debug info
	 *
	 * @ignore
	 */
	public $debug = FALSE;

	/**
	 * Set API URLS
	 */
	abstract public function accessTokenURL();
	
	abstract public function authorizeURL();

	/**
	 * construct self object
	 */
	public function __construct($client_id, $client_secret) {
		$this->client_id = $client_id;
		$this->client_secret = $client_secret;
	}
	
	public function setCurlOptions(array $options){
		$this->_curlOptions = array_merge($this->_curlOptions, $options);
	}
	
	/**
	 * authorize接口
	 * 
	 * @param array $params
	 * @return string
	 */
	public function getAuthorizeURL(array $params) {
		$defaults = array(
			'client_id'	=> $this->client_id,
			'response_type'=> 'code',
		);
		
		return $this->authorizeURL() . "?" . http_build_query($params + $defaults);
	}
	
	/**
	 * access_token接口
	 *
	 * @link http://open.weibo.com/wiki/OAuth2/access_token OAuth2/access_token
	 *
	 * @param string $type 请求的类型,可以为:code, password, token
	 * @param array $keys 其他参数：
	 *  - 当$type为code时： array('code'=>..., 'redirect_uri'=>...)
	 *  - 当$type为password时： array('username'=>..., 'password'=>...)
	 *  - 当$type为token时： array('refresh_token'=>...)
	 * @return array
	 */
	public function getAccessToken( $type = 'code', $keys ) {
		$params = $keys;
		$params['client_id'] = $this->client_id;
		$params['client_secret'] = $this->client_secret;
		if ( $type === 'token' ) {
			$params['grant_type'] = 'refresh_token';
			$params['refresh_token'] = $keys['refresh_token'];
		} elseif ( $type === 'code' ) {
			$params['grant_type'] = 'authorization_code';
			$params['code'] = $keys['code'];
			$params['redirect_uri'] = $keys['redirect_uri'];
		} elseif ( $type === 'password' ) {
			$params['grant_type'] = 'password';
			$params['username'] = $keys['username'];
			$params['password'] = $keys['password'];
		} else {
			throw new Exception("wrong auth type");
		}

		$response = $this->http($this->accessTokenURL(), 'POST', http_build_query($params));
		
		return $this->_tokenFilter($response);
	}
	
	protected function _tokenFilter($response){
		return json_decode($response, true);
	}

	/**
	 * 
	 * @return array
	 */
	protected function _additionalHeaders(){
		return array();
	}
	
	/**
	 * Make an HTTP request
	 *
	 * @param string $url
	 * @param string $method
	 * @param string $postfields
	 * @param array $headers
	 * @throws CurlException
	 * @return string API results
	 */
	public function http($url, $method, $postfields = NULL, $headers = array()) {
		$this->http_info = array();
		$ci = curl_init();
		/* Curl settings */
		curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ci, CURLOPT_ENCODING, "");
		curl_setopt($ci, CURLOPT_HEADERFUNCTION, array($this, 'getHeader'));
		curl_setopt($ci, CURLOPT_HEADER, FALSE);
		curl_setopt($ci, CURLOPT_URL, $url);
		curl_setopt($ci, CURLOPT_HTTPHEADER, array_merge($headers, $this->_additionalHeaders()));
		curl_setopt($ci, CURLINFO_HEADER_OUT, TRUE);
		
		curl_setopt_array($ci, $this->_curlOptions);

		switch ($method) {
			case 'POST':
				curl_setopt($ci, CURLOPT_POST, TRUE);
				break;
			case 'GET':
				curl_setopt($ci, CURLOPT_POST, FALSE);
				break;
			default:
				curl_setopt($ci, CURLOPT_CUSTOMREQUEST, $method);
		}

		if (!empty($postfields))
			curl_setopt($ci, CURLOPT_POSTFIELDS, $postfields);

		$response = curl_exec($ci);
		
		if ($response === false){	//	modified by shen2
	    	$exception = new CurlException(curl_error($ci), curl_errno($ci));
	    	curl_close($ci);
	    	throw $exception;
	    }
		
		$this->http_info = array_merge($this->http_info, curl_getinfo($ci));
		$this->url = $url;

		if ($this->debug) {
			echo "=====post data======\r\n";
			var_dump($postfields);

			echo '=====info====='."\r\n";
			print_r( curl_getinfo($ci) );

			echo '=====$response====='."\r\n";
			print_r( $response );
		}
		curl_close ($ci);
		return $response;
	}

	/**
	 * Get the header info to store.
	 *
	 * @return int
	 * @ignore
	 */
	public function getHeader($ch, $header) {
		$i = strpos($header, ':');
		if (!empty($i)) {
			$key = str_replace('-', '_', strtolower(substr($header, 0, $i)));
			$value = trim(substr($header, $i + 2));
			$this->http_header[$key] = $value;
		}
		return strlen($header);
	}
}
