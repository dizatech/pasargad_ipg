## Payment Cycle
For a payment transaction we have to request a purchase via webservice. If our request is successful the IPG will return a url which we should use while redirecting customer to payment page. Customer will be redirected back to our desired URL(redirect address) from payment page via a GET request carrying data which may be used to check and verify customer's transaction using web service.

### Instantiating an IPG object
for instantiating an IPG object we should call `Dizatech\PasargadIpg\PasargadIpg` constructor passing it an array of required arguments containing:
* username: your payment gateway username
* password: your payment gateway password
* terminal_number: your payment gateway terminal number
#### Code sample:
```php
$args = [
    'username'          => '123',
    'password'          => '456',
    'terminal_number'   => '789',
]; //Replace arguments with your gateway actual values 
$ipg = new Dizatech\PasargadIpg\PasargadIpg($args);
```
### Purchase Request
For a payment transaction we should request a purchase from IPG and acquire a payment url. This may be accomplished by calling `purchase` method. If the request is successful we can redirect our customer to the acquired payment url.
#### Arguments:
* amount: payment amount in Rials
* invoice_number: unique invoice number
* invoice_date: invoice date in desired format
* redirect_address: URL to which customer may be redirected after payment
#### Returns:
An object with the following properties:
* status: `success` or `error`
* url id: in case of a successful request contains the a url id which may be used for further tracking the purchase request
* payment url: in case of a successful request contains a payment url to which we should redirect our customer
#### Code sample:
```php
$purchase = $ipg->purchase(
    amount: 20000,
    invoice_number: 1,
    invoice_date: '2024-06-29 10:20:30',
    redirect_address: 'http://myaddress.com/verify'
); //Replace arguments with your gateway actual values 
if ($purchase->status == 'success') {
    header("Location: {$purchase->payment_url}");
    exit;
}    
```
## Payment check and verification
After payment the customer will be redirected back to the redirect address provided in purchase phase via a GET request carrying all necessary data. Data fields sent by IPG are:
* status: status of customer payment
* invoiceId: the invoice number sent to IPG in purchase phase; we can use this invoice id for acquiring original invoice from our database
* referenceNumber: reference number which may be used for further tracking the payment
* trackId: tracking id which may be used for further tracking the payment
  
If `status` equals `sucecss` it may considered as a successful payment claim which should be inquired and verified. Anything other than `success` means the payment has failed; thus, there will be no need for any further action.
It should be noted that successful payments have to verified. otherwise they will be returned to customer's bank account.

### Inquiry
Successful payment data should be inquired before we could verify them. Inquiry can be accomplished by calling `inquiry` method.
#### Arguments:
* invoice_number: invoice number used in purchase phase for which the user has been sent to payment gateway
#### Returns:
* status: inquiry request status which should be success; otherwise it means that our request has totally failed; thus we don't have any data related to the payment.
* payment_status: payment status which may be `success`, `confirmed` or `refunded`. `success` as payment status means the payment has been successful and we can move on and verify the payment. `confirmed` means the payment has already been verified and also confirmed (can't be refunded and will be transferred to the merchant's bank account). `refunded` payments are successful payments but has been returned to the payer's bank account either because of deliberate refunding or failure in verification.
* transaction_id: transaction id which may be used for further tracking the payment
* reference_number: reference number which may be used for further tracking the payment
* amount: the actual paid amount which should match the original invoice amount
* pan: the customer's PAN number
* url_id: url id which may be used to retrieve original invoice from our database, verifying or further tracking the payment
#### Code Sample:
```php
$inquiry = $ipg->inquiry(invoice_number: 1);
//Replace arguments with your gateway actual values 
```
### Verify
If the inquiry result is `success` and payment result is also `success` we have to verify the transaction via `verify` method.
#### Arguments:
* invoice_number: invoice number used in purchase phase for which the user has been sent to payment gateway
* url_id: url id returned from inquiry phase
#### Returns:
* status: verify request status which may be `success` or `error`
* reference_number: reference number which may be used for further tracking the payment
* amount: the actual paid amount which should match the original invoice amount
* pan: the customer's PAN number
#### Code Sample:
```php
//Replace arguments with your gateway actual values 
$inquiry = $ipg->inquiry(invoice_number: 1);
if ($inquiry->status == 'success' && $inquiry->payment_status == 'success') {
    $verification_result = $ipg->verify(
        invoice_number: 1,
        url_id: '2.................004497985'
    );
    if ($verification_result->status == 'success') {
        echo $inquiry->transaction_id . "<br>";
        echo $verification_result->reference_number . "<br>";
        echo $verification_result->pan . "<br>";
        echo $inquiry->url_id;
        die();
    } else {
        die('Failed');
    }
} elseif ($inquiry->status == 'success' && $inquiry->payment_status == 'refunded') {
    die('Refunded');
}
```
## Refund
In case we need to cancel customer order immediately after payment (maximum 2 hours later) we can simply refund the payment transaction which may result to full and instant refund to customer's bank account. For refunding transactions we can call `refund` method.
#### Arguments:
* invoice_number: invoice number used in purchase phase for which the user has been sent to payment gateway
* url_id: url id returned from inquiry or purchase phase
#### Returns:
* status: refund request status which may be `success` or `error`
* error_message: in case of `error` it may contain an error message
#### Code Sample:
```php
$refund = $ipg->refund(
    invoice_number: 1,
    url_id: '2.................004497985'
); //Replace arguments with your gateway actual values 
```