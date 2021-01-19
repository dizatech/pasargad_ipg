<?php

namespace Dizatech\PasargadIpg;

class PasargadIpg{
    
    protected $merchant_code;
    protected $terminal_code;
    protected $private_key;
    protected $base_url = 'https://pep.shaparak.ir/Api/v1/Payment/';
    protected $verify_ssl = TRUE;

    public function __construct( $args=[] )
    {
        $this->merchant_code = $args['merchant_code'];
        $this->terminal_code = $args['terminal_code'];
        $this->private_key = $args['private_key'];
    }
    
    public function verifySSL( bool $verify=TRUE ){
		$this->verify_ssl = $verify;
	}
    
    public function getToken( $amount, $invoice_number, $invoice_date, $redirect_address )
    {
        $rsa = new Rsa( $this->private_key );
        $params = [];
        $params['amount'] = $amount;
        $params['invoiceNumber'] = $invoice_number;
        $params['invoiceDate'] = $invoice_date;
        $params['action'] = '1003'; 	
        $params['merchantCode'] = $this->merchant_code;
        $params['terminalCode'] = $this->terminal_code;
        $params['redirectAddress'] = $redirect_address;
        $params['timeStamp'] = date('Y/m/d H:i:s');
        
        $signature = base64_encode( $rsa->sign( sha1( json_encode($params), TRUE ) ) );
        $result = $this->send_request( $this->base_url . 'GetToken', json_encode( $params ), $signature );
        $result = json_decode( $result );
        
        $response = new \stdClass();
        if( $result->IsSuccess ){
            $response->status = 'success';
            $response->token = $result->Token;
        }
        else{
            $response->status = 'error';
            $response->message = $result->Message;
        }
        
        return $response;
    }
    
    public function checkTransaction( $invoice_number, $invoice_date )
    {
        $params = [];
        $params['invoiceNumber'] = $invoice_number;
        $params['invoiceDate'] = $invoice_date;
        $params['merchantCode'] = $this->merchant_code;
        $params['terminalCode'] = $this->terminal_code;
        $params['timeStamp'] = date("Y/m/d H:i:s");
        
        $result = $this->send_request( $this->base_url . 'CheckTransactionResult', json_encode( $params ) );
        $result = json_decode( $result );
        
        $response = new \stdClass();
        if( $result->IsSuccess ){
            $response->status = 'success';
            $response->amount = $result->Amount;
        }
        else{
            $response->status = 'error';
            $response->message = $result->Message;
        }
        
        return $response;
    }
    
    public function verifyTransaction( $amount, $invoice_number, $invoice_date )
    {
        $rsa = new Rsa( $this->private_key );
        $params = [];
        $params['amount'] = $amount;
        $params['invoiceNumber'] = $invoice_number;
        $params['invoiceDate'] = $invoice_date;
        $params['merchantCode'] = $this->merchant_code;
        $params['terminalCode'] = $this->terminal_code;
        $params['timeStamp'] = date("Y/m/d H:i:s");
        
        $signature = base64_encode( $rsa->sign( sha1( json_encode($params), TRUE ) ) );
        $result = $this->send_request( $this->base_url . 'VerifyPayment', json_encode( $params ), $signature );
        $result = json_decode( $result );
        
        $response = new \stdClass();
        if( $result->IsSuccess ){
            $response->status = 'success';
        }
        else{
            $response->status = 'error';
            $response->message = $result->Message;
        }
        
        return $response;
    }
    
    public function refundPayment( $invoice_number, $invoice_date )
    {
        $rsa = new Rsa( $this->private_key );
        $params = [];
        $params['invoiceNumber'] = $invoice_number;
        $params['invoiceDate'] = $invoice_date;
        $params['merchantCode'] = $this->merchant_code;
        $params['terminalCode'] = $this->terminal_code;
        $params['timeStamp'] = date("Y/m/d H:i:s");
        
        $signature = base64_encode( $rsa->sign( sha1( json_encode($params), TRUE ) ) );
        $result = $this->send_request( $this->base_url . 'RefundPayment', json_encode( $params ), $signature );
        $result = json_decode( $result );
        
        $response = new \stdClass();
        if( $result->IsSuccess ){
            $response->status = 'success';
        }
        else{
            $response->status = 'error';
            $response->message = $result->Message;
        }
        
        return $response;
    }

    protected function send_request( $url, $message, $signature="" )
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
        if( !$this->verify_ssl ){
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		}

        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Accept: application/json';
        if($signature != ''){
            $headers[] = 'Sign: '.$signature;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $header_size);
        curl_close($ch);
        return $body;
    }
    
}
