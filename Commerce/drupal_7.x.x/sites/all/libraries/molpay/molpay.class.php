<?php

/**
 * @file    Integrate MOLPay payment gateway system.
 * @date    2012-3-30
 * @version 1.0
 * @author  technical@molpay.com
 */
class MOLPay {

    // Payment methods, please view technical spec for latest update.
    public static $payment_methods = array(
        array(54, 'Alipay', 'USD'),
        array(8, 'Alliance Online Transfer', 'MYR'),
        array(10, 'AmBank', 'MYR'),
        array(21, 'China Union Pay', 'MYR'),
        array(20, 'CIMB Clicks', 'MYR'),
        array(39, 'Credit Card', 'AUD'),
        array(37, 'Credit Card', 'CAD'),
        array(41, 'Credit Card', 'EUR'),
        array(35, 'Credit Card', 'GBP'),
        array(42, 'Credit Card', 'HKD'),
        array(46, 'Credit Card', 'IDR'),
        array(45, 'Credit Card', 'INR'),
        array(2, 'Credit Card', 'MYR'),
        array(40, 'Credit Card', 'MYR'), // For multi-currency only
        array(47, 'Credit Card', 'PHP'),
        array(38, 'Credit Card', 'SGD'),
        array(36, 'Credit Card', 'THB'),
        array(50, 'Credit Card', 'TWD'),
        array(25, 'Credit Card', 'USD'),
        array(16, 'FPX', 'MYR'),
        array(15, 'Hong Leong Bank Transfer', 'MYR'),
        array(6, 'Maybank2U', 'MYR'),
        array(23, 'Meps Cash', 'MYR'),
        array(17, 'Mobile Money', 'MYR'),
        array(32, 'Payeasy', 'PHP'),
        array(33, 'PayPal', 'USD'),
        array(53, 'Paysbuy (Credit Card only)', 'THB'),
        array(14, 'RHB', 'MYR'),
    );

    public static $referer_url = 'www.molpay.com'; // without scheme (http/https)

    private $merchant_vkey = '';  // Private key, do not share!

    // Details to be sent to MOLPay for payment request.
    private $payment_request = array(
        'amount'        => '',
        'orderid'	=> '',
        'bill_name'	=> '',
        'bill_email'	=> '',
        'bill_mobile'	=> '',
        'bill_desc'	=> '',
        'country'	=> '',
        'cur'		=> '',
        'returnurl'	=> '',
        'vcode'		=> ''
    );

  /* Return response from molpay:
   * - amount
   * - orderid
   * - appcode
   * - tranID
   * - domain
   * - status
   * - currency
   * - paydate
   * - channel
   * - skey
   */

  /**
   * Validate the data given by user according to the rules specified by MOLPay API.
   *
   * @param string $field The field to check.
   * @param string $data  Data supplied by user.
   *
   * @return boolean TRUE if passed validation and vice-versa.
   */
    public function validateField($field, $data) {
        switch ($field) {
            case 'amount':
                if (preg_match('^[0-9]+\.[0-9]{2}$^', $data)) return TRUE;
                break;
            case 'cur':
                if (strlen($data) == 3) return TRUE;
                break;
            case 'vcode':
            case 'orderid':
            case 'bill_desc':
            case 'country':
            case 'bill_name':
            case 'bill_email':
            case 'bill_mobile':
            case 'returnurl':
                return TRUE;
                break;
      }

      return FALSE;
    }

    /**
     * Return all the fields (normally after setField() method is called).
     * Can be used to populate forms.
     *
     * @return array Payment method fields.
     */
    public function getFields() {
        return $this->payment_request;
    }

    /**
     * Return individual field values.
     *
     * @param string $field Field name.
     * @return string Value of the field. If field name is invalid, returns FALSE.
     */
    public function getField($field) {
        return (isset($this->payment_request[$field]) ? $this->payment_request[$field] : FALSE);
    }

    /**
     * Get info about payment method.
     *
     * @param int $payment_id Payment method ID.
     *
     * @return array Name and currency of payment method.
     */
    public function getPaymentMethod($payment_id) {
        foreach (self::$payment_methods as $val) {
            if ($val[0] === $payment_id) {
                return array(
                    'name' => isset($val[1]) ? trim($val[1]) : NULL,
                    'currency' => isset($val[2]) ? strtoupper(trim($val[2])) : NULL,
                );
            }
        }
    }

    /**
     * Wrapper method to receive response and return status. If transaction was successful, a requery will be done to double-check.
     *
     * @param boolean $requery     Whether to requery MOLPay server for transaction confirmation.
     * @param boolean $return_data Whether to return data back.
     *
     * @return array Status of the transaction and processed response.
     */
    public function getResponse($return_data = TRUE) {
        $return = array(
            'data' => array(),
        );

        $data = $_POST;
        $return['status'] 	= isset($data['status']) 	? $data['status'] 	: FALSE;
        $return['orderid'] 	= isset($data['orderid']) 	? $data['orderid'] 	: FALSE;
        $return['amount'] 	= isset($data['amount']) 	? $data['amount'] 	: FALSE;
        $return['appcode'] 	= isset($data['appcode']) 	? $data['appcode'] 	: FALSE;
        $return['tranID'] 	= isset($data['tranID']) 	? $data['tranID'] 	: FALSE;
        $return['domain'] 	= isset($data['domain']) 	? $data['domain'] 	: FALSE;
        $return['currency'] = isset($data['currency']) 	? $data['currency'] : FALSE;
        $return['paydate'] 	= isset($data['paydate']) 	? $data['paydate'] 	: FALSE;
        $return['channel'] 	= isset($data['channel']) 	? $data['channel'] 	: FALSE;
        $return['skey'] 	= isset($data['skey']) 		? $data['skey'] 	: FALSE;

        if ($return_data) {
            $return['data'] = $data;
        }
        return $return;
    }

    /**
     * Set variable to field. Data supplied will be validated before it is set and any error found will be thrown to user.
     *
     * @param string $field The field name to set.
     * @param string $data  Data supplied by user.
     */
    public function setField($field, $data) {
        if ($this->validateField($field, $data)) {
            /*switch ($field) {
              case 'cur':
                //$data = strtoupper($data);
                break;
            }*/

            $this->payment_request[$field] = $data;
        } else {
            echo "<pre>";
            echo print_r($field);
            // Return error message.
            $field = "<em>$field</em>";
            $error_msg = "Failed validation for $field. ";
            switch (strip_tags($field))  {
              case 'amount':
                $error_msg .= "$field must be a number with 2 decimal points.";
                break;
              case 'cur':
                $error_msg .= "$field must 3 characters in length.";
                break;
                      case 'orderid':
              case 'vcode':
              case 'bill_name':
              case 'bill_desc':
              case 'bill_email':
              case 'bill_mobile':
                      case 'country':
              case 'returnurl':
            }
            trigger_error(trim($error_msg));
        }
  }

    /**
     * Separate method to set merchant key, not sharing with setField() due to privacy concern.
     *
     * @param string $key Private key for merchant.
     */
    public function setMerchantKey($key) {
        $this->merchant_vkey = $key;
    }

    /**
     * Receives response returned from MOLPay server after payment is processed.
     *
     * @param array $response Response returned from MOLPay server after transaction is processed.
     *
     * @return boolean Only returns FALSE for failed transaction. You should only check for FALSE status.
     */
    public function validateResponse($response) {
        // Check referer, must be from mobile88.com only
        // Only valid if payment went through MOLPay.
        $referer = parse_url($_SERVER['HTTP_REFERER']);
        if ($referer['host'] != self::$referer_url) {
            trigger_error('Referer check failed, mismatch with www.molpay.com.');
            return FALSE;
        }
    }

    /**
     * Check payment status (re-query).
     *
     * @param array $payment_details The following variables are required:
     * - MerchantCode
     * - RefNo
     * - Amount
     *
     * @return string Possible payment status from MOLPay server:
     * - 00                 - Successful payment
     * - Invalid parameters - Parameters passed is incorrect
     * - Record not found   - Could not find the record.
     * - Incorrect amount   - Amount differs.
     * - Payment fail       - Payment failed.
     * - M88Admin           - Payment status updated by Mobile88 Admin (Fail)
     */
    /*public function requery($payment_details) {
      if (!function_exists('curl_init')) {
        trigger_error('PHP cURL extension is required.');
        return FALSE;
      }

      $curl = curl_init(self::$requery_url . '?' . http_build_query($payment_details));
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
      $result = trim(curl_exec($curl));
      //$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      curl_close($curl);

      return $result;
    }*/
	
	public function ipn()
	{
		/***********************************************************
		* Snippet code in purple​color is the enhancement required
		* by merchant to add into their return script in order to
		* implement backend acknowledge method for IPN
		************************************************************/
		while ( list($k,$v) = each($_POST) ) 
		{
			$postData[]= $k."=".$v;
		}
		$postdata 	=implode("&",$postData);
		$url 		="https://www.onlinepayment.com.my/MOLPay/API/chkstat/returnipn.php";
		$ch 		=curl_init();
		curl_setopt($ch, CURLOPT_POST , 1 );
		curl_setopt($ch, CURLOPT_POSTFIELDS , $postdata );
		curl_setopt($ch, CURLOPT_URL , $url );
		curl_setopt($ch, CURLOPT_HEADER , 1 );
		curl_setopt($ch, CURLINFO_HEADER_OUT , TRUE );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER , 1 );
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER , FALSE);
		$result = curl_exec( $ch );
		curl_close( $ch );
	}
}
