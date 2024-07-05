<?php

namespace Dizatech\PasargadIpg;

use Exception;
use GuzzleHttp\Client;

class PasargadIpg
{
    protected $username;
    protected $password;
    protected $terminal_number;
    protected $client;
    protected $verify_ssl = TRUE;

    const BASE_URI = 'https://pep.shaparak.ir/dorsa1';

    public function __construct($args = [])
    {
        $this->username = $args['username'];
        $this->password = $args['password'];
        $this->terminal_number = $args['terminal_number'];
        $this->client = new Client();
    }

    public function verifySSL(bool $verify = TRUE)
    {
        $this->verify_ssl = $verify;
    }

    public function purchase($amount, $invoice_number, $invoice_date, $redirect_address)
    {
        $result = new \stdClass();
        $result->status = 'error';
        $token = $this->getToken();

        if ($token) {
            try {
                $response = $this->client->post(
                    self::BASE_URI . '/api/payment/purchase',
                    [
                        'headers'   => [
                            'Authorization'     => "Bearer {$token}",
                        ],
                        'body'      => json_encode([
                            'amount'            => strval($amount),
                            'invoice'           => strval($invoice_number),
                            'invoiceDate'       => $invoice_date,
                            'callbackApi'       => $redirect_address,
                            'serviceCode'       => 8,
                            'serviceType'       => 'PURCHASE',
                            'terminalNumber'    => $this->terminal_number,
                        ]),
                    ]
                );

                if ($response->getStatusCode() == 200) {
                    $response = json_decode($response->getBody()->getContents());
                    if ($response && isset($response->resultCode) && $response->resultCode == 0) {
                        $result->status = 'success';
                        $result->url_id = $response->data->urlId;
                        $result->payment_url = $response->data->url;
                    }
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
        }

        return $result;
    }

    public function inquiry($invoice_number)
    {
        $result = new \stdClass();
        $result->status = 'error';
        $token = $this->getToken();

        if ($token) {
            try {
                $response = $this->client->post(
                    self::BASE_URI . '/api/payment/payment-inquiry',
                    [
                        'headers'   => [
                            'Authorization'     => "Bearer {$token}",
                        ],
                        'body'      => json_encode([
                            'invoiceId'         => strval($invoice_number),
                        ]),
                    ]
                );

                if ($response->getStatusCode() == 200) {
                    $response = json_decode($response->getBody()->getContents());
                    if ($response && isset($response->resultCode) && $response->resultCode == 0) {
                        switch ($response->data->status) {
                            case 2:
                                $result->status = 'success';
                                $result->payment_status = 'success';
                                break;
                            case 13029:
                                $result->status = 'success';
                                $result->payment_status = 'confirmed';
                                break;
                            case 13033:
                                $result->status = 'success';
                                $result->payment_status = 'refunded';
                                break;
                        }
                        $result->transaction_id = $response->data->transactionId;
                        $result->reference_number = $response->data->referenceNumber;
                        $result->amount = $response->data->amount;
                        $result->pan = $response->data->cardNumber;
                        $result->url_id = $response->data->url;
                    } elseif (isset($response->resultCode)) {
                        error_log("Payment inquiry for invoice {$invoice_number} failed with {$response->resultCode} error.");
                    } else {
                        error_log("Payment inquiry for invoice {$invoice_number} failed.");
                    }
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
        }

        return $result;
    }

    public function verify($invoice_number, $url_id)
    {
        $result = new \stdClass();
        $result->status = 'error';
        $token = $this->getToken();

        if ($token) {
            try {
                $response = $this->client->post(
                    self::BASE_URI . '/api/payment/verify-payment',
                    [
                        'headers'   => [
                            'Authorization'     => "Bearer {$token}",
                        ],
                        'body'      => json_encode([
                            'invoice'           => strval($invoice_number),
                            'urlId'             => $url_id,
                        ]),
                    ]
                );

                if ($response->getStatusCode() == 200) {
                    $response = json_decode($response->getBody()->getContents());
                    if ($response && isset($response->resultCode) && $response->resultCode == 0) {
                        $result->status = 'success';
                        $result->reference_number = $response->data->referenceNumber;
                        $result->amount = $response->data->amount;
                        $result->pan = $response->data->maskedCardNumber;
                    } else {
                        error_log("Verifying transaction for invoice {$invoice_number} failed.");
                    }
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
        }

        return $result;
    }

    public function refund($invoice_number, $url_id)
    {
        $result = new \stdClass();
        $result->status = 'error';
        $token = $this->getToken();

        if ($token) {
            $inquiry = $this->inquiry(invoice_number: $invoice_number);
            if ($inquiry->status == 'success') {
                if ($inquiry->payment_status == 'success') {
                    try {
                        $response = $this->client->post(
                            self::BASE_URI . '/api/payment/reverse-transactions',
                            [
                                'headers'   => [
                                    'Authorization'     => "Bearer {$token}",
                                ],
                                'body'      => json_encode([
                                    'invoice'           => strval($invoice_number),
                                    'urlId'             => $url_id,
                                ]),
                            ]
                        );
        
                        if ($response->getStatusCode() == 200) {
                            $response = json_decode($response->getBody()->getContents());
                            if ($response && isset($response->resultCode) && $response->resultCode == 0) {
                                $result->status = 'success';
                            } else {
                                error_log("Reversing transaction for invoice {$invoice_number} failed.");
                            }
                        }
                    } catch (Exception $e) {
                        error_log($e->getMessage());
                    }
                } elseif ($inquiry->payment_status == 'verified') {
                    $result->error_message = 'تراکنش مورد نظر تایید نهایی شده و قابل برگشت نیست.';
                } elseif ($inquiry->payment_status == 'refunded') {
                    $result->error_message = 'تراکنش مورد نظر قبلا برگشت داده شده است.';
                }
            } else {
                $result->error_message = 'تراکنش مورد نظر ناموفق است.';
            }
        }

        return $result;
    }

    public function getToken()
    {
        $response = $this->client->post(
            self::BASE_URI . '/token/getToken',
            [
                'body'      => json_encode([
                    'username'  => $this->username,
                    'password'  => $this->password,
                ]),
            ]
        );
        if ($response->getStatusCode() == 200) {
            $response = json_decode($response->getBody()->getContents());
            if ($response && isset($response->resultCode) && $response->resultCode == 0) {
                return $response->token;
            }
        }

        error_log('Failed to connect to Pasargad IPG API to acquire token');
        return false;
    }
}
