<?php
/**
 * First Data Global Gateway Web Service API for PHP
 * Supports Credit Cards only
 *
 * @author Joshua Padgett
 * 2012-11-21
 * 
 */
class FirstData {
  private $postingURL;
	private $store;
	private $userId;
	private $pass;
	private $sslCert;
	private $sslKey;
	private $sslKeyPass;
  public $oid;
  private $config;
  private $totals;
  private $cardInfo;
  private $billingInfo;
  
  /* function: __construct
   * Constructs the object.
   * Options array will be appended to the charge request. Add your custom fields here.
   * Required Params: sharedKey, store, oid(public - can be set later)
   * Optional Params: oid, config(array), options(array)
   * 
   * txtntype Options: ECI, MOTO, RETAIL
   */
  public function __construct($postingURL,$store,$userID,$pass,$sslCert,$sslKey,$sslKeyPass,$oid = '',$config = array()) {
    if (!$postingURL) {
      throw new Exception('POST URL Required.');
    } else {
      $this->postingURL = $postingURL;
    }
		if (!$store) {
      throw new Exception('Store Required.');
    } else {
      $this->store = $store;
    }
		if (!$userID || !$pass || !$sslCert || !$sslKey || !$sslKeyPass) {
      throw new Exception('User ID, Password, SSL Cert, SSL Key, and SSL Key Password Required.');
    } else {
			$this->userId = $userID;
			$this->pass = $pass;
			$this->sslCert = $sslCert;
			$this->sslKey = $sslKey;
			$this->sslKeyPass = $sslKeyPass;
		}
    
    if ($oid) $this->oid = $oid;
    
    $this->config = array(
        'txtntype'    => (empty($config['txtntype'])) ? 'sale' : $config['txtntype'],
        'timezone'    => (empty($config['timezone'])) ? date('T') : $config['timezone'],
        'mode'        => (empty($config['mode'])) ? 'payonly' : $config['mode'],
        'trxOrigin'   => (empty($config['trxOrigin'])) ? 'ECI' : $config['trxOrigin']
    );
  }
  
  public function __destruct() {
    $this->sharedKey = null;
    unset($this->sharedKey);
    $this->store = null;
    unset($this->store);
    $this->cardinfo = null;
    unset($this->cardInfo);
  }
  
  /* function: setTotals
   * 
   */
  public function setTotals($subtotal,$tax = 0,$shipping = 0) {
    $this->totals = array(
        'subtotal'    => number_format($subtotal, 2, '.', ''),
        'tax'         => number_format($tax, 2, '.', ''),
        'shipping'    => number_format($shipping, 2, '.', '')
    );
    $chargetotal = $this->totals['subtotal'] + $this->totals['tax'] + $this->totals['shipping'];
    $chargetotal = number_format($chargetotal, 2, '.', '');
    $this->totals['chargetotal'] = $chargetotal;
  }
  
  /* function: setCardInfo
   * Input: cardType(M, V, A, C, J, D), cardNum, expMonth, expYear, cvv, billingInfo(array)
   * M = Mastercard, V = Visa, A = Amex, C = Diners, J = JCB, D = Discover
   */
  public function setCardInfo($cardType,$cardNum,$expMonth,$expYear,$cvv,$billingInfo = array()) {
    $cardTypes = array('M','V','A','C','J','D');
    if (!$cardType || !$cardNum || !$expMonth || !$expYear || !$cvv) {
      throw new Exception('Complete card info required.');
    }
    if (!in_array(strtoupper($cardType), $cardTypes)) {
      throw new Exception('Card type invalid.');
    }
    
    $this->cardInfo = array(
        'cardnumber'  => $cardNum,
        'expmonth'    => $expMonth,
        'expyear'     => $expYear,
        'cvm'         => $cvv
    );
    
    //Setup the billing info if it's been passed in.
    if ($billingInfo) {
      $this->setBillingInfo($billingInfo);
    }
  }
  
  /* function: setBillingInfo
   * 
   */
  public function setBillingInfo($billingInfo) {
    $this->billingInfo = array(
        'bcompany'  => $billingInfo['company'],
        'bname'     => $billingInfo['name'],
        'baddr1'    => $billingInfo['addr1'],
        'baddr2'    => $billingInfo['addr2'],
        'bcity'     => $billingInfo['city'],
        'bstate'    => $billingInfo['state'],
        'bstate2'   => $billingInfo['state2'],
        'bcountry'  => $billingInfo['country'],
        'bzip'      => $billingInfo['zip'],
        'phone'     => $billingInfo['phone'],
        'fax'       => $billingInfo['fax'],
        'email'     => $billingInfo['email']
    );
  }
  
  /* function: chargeIt
   * Charges the card
   */
  public function chargeIt() {    
    
		$sc = new SoapClient(null, array(
				'encoding'			=>'UTF-8',
				'soap_version'	=> SOAP_1_2,
				'exceptions'		=> true,
				'cache_wsdl'		=> WSDL_CACHE_NONE,
				'location'			=> $this->postingURL,
				'uri'						=> $this->postingURL, //'https://ws.merchanttest.firstdataglobalgateway.com/fdggwsapi/schemas_us/fdggwsapi.xsd',
				'login'					=> $this->userId,
				'password'			=> $this->pass,
				'local_cert'		=> $this->sslKey,
				'passphrase'		=> $this->sslKeyPass
		));
		//var_dump($sc);exit;
		try {
			$args = array();
			$response = $sc->__soapCall('FDGGWSApiOrderRequest',$args);
			var_dump($response);exit;
		} catch (SoapFault $e) {
      //echo $e->faultcode.' '.$e->faultstring;exit;
			var_dump($e);exit;
    }
		
		/*** Let's build our curl request ***/
		$SOAPbody = '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">';
		$SOAPbody .= '<SOAP-ENV:Header /><SOAP-ENV:Body>';
		
		$SOAPbody .= '</SOAP-ENV:Body></SOAP-ENV:Envelope>';
		
		//echo $SOAPbody.'<br /><br />';
		
    $ch = curl_init($this->postingURL);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		//curl_setopt($ch, CURLOPT_USERPWD, base64_encode('WS'.$this->store.'._.1:'.$this->pass));
		curl_setopt($ch, CURLOPT_USERPWD, 'WS'.$this->store.'._.1:'.$this->pass);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $SOAPbody);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSLCERT, $this->sslCert);
		curl_setopt($ch, CURLOPT_SSLKEY, $this->sslKey);
		curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $this->sslKeyPass);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		/*** ***/
		
		try {
			$response = curl_exec($ch);

			if($response === false)
			{
				throw new Exception('Curl error: '.curl_error($ch));
			}
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
		
			
			/*** Handle Response ***/
//			if ($httpCode >= 400) {
//				//throw new Exception('HTTP Error: '.$httpCode.'<br /><br />\n\n'.$response);
//				throw new Exception($response);
//			}
			//TODO: Actually handle this properly
			
			// SimpleXML seems to have problems with the colon ":" in the <xxx:yyy> response tags, so take them out 
			//$xmlString = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $response);
			$response = str_replace('fdggwsapi:', '', $response);
			//die($response);
			$return = simplexml_load_string($response,null,null,'http://schemas.xmlsoap.org/soap/envelope/');
			//$return->registerXPathNamespace('fdggwsapi', 'https://ws.merchanttest.firstdataglobalgateway.com/fdggwsapi/schemas_us/fdggwsapi.xsd');
			//http://secure.linkpt.net/fdggwsapi/schemas_us/fdggwsapi
			//$return = simplexml_load_string($xmlString);
			//return $return->Body->children('https://ws.merchanttest.firstdataglobalgateway.com/fdggwsapi/schemas_us/fdggwsapi.xsd'); //For Testing
			return $return;

			//Let's wipe the card info from memory, just to be safe.
			$this->cardInfo = null;
			unset($this->cardInfo);
		} catch (Exception $e) {
			die($e->getMessage());
		}
  }
  
  /* function: setOptions
   * Replaces or clears existing options
   */
  public function setOptions($options = array()) {
    $this->options = array();
    foreach ($options as $key => $val) {
      $this->options[$key] = $val;
    }
  }
  
  /* function: addOptions
   * Appends to or updates existing options
   */
  public function addOptions($options) {
    foreach ($options as $key => $val) {
      $this->options[$key] = $val;
    }
  }
  
  /* function: createHash
   * Creates SHA2 hash for authentication to gateway
   */
  private function createHash($dateTime) {
    $str = $this->store.$dateTime.$this->totals['chargetotal'].$this->sharedKey;
    $hex_str = '';
    for ($i = 0; $i < strlen($str); $i++){ 
      $hex_str .= dechex(ord($str[$i]));
    }
    return hash('sha256', $hex_str);
  }
   
}

?>
