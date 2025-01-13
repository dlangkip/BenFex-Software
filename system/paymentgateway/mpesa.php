<?php


function mpesa_validate_config()
{
    global $config;
    if (empty($config['mpesa_consumer_key']) || empty($config['mpesa_consumer_secret']) || 
        empty($config['mpesa_shortcode']) || empty($config['mpesa_passkey'])) {
        sendTelegram("M-Pesa payment gateway not configured");
        r2(U . 'order/package', 'w', Lang::T("Admin has not yet setup M-Pesa payment gateway, please tell admin"));
    }
}

function mpesa_show_config()
{
    global $ui;
    $ui->assign('_title', 'M-Pesa - Payment Gateway');
    $ui->display('mpesa.tpl');
}

function mpesa_save_config()
{
    global $admin, $_L;
    $consumer_key = _post('consumer_key');
    $consumer_secret = _post('consumer_secret');
    $shortcode = _post('shortcode');
    $passkey = _post('passkey');
    $env = _post('env');

    $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_consumer_key')->find_one();
    if ($d) {
        $d->value = $consumer_key;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'mpesa_consumer_key';
        $d->value = $consumer_key;
        $d->save();
    }

    $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_consumer_secret')->find_one();
    if ($d) {
        $d->value = $consumer_secret;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'mpesa_consumer_secret';
        $d->value = $consumer_secret;
        $d->save();
    }

    $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_shortcode')->find_one();
    if ($d) {
        $d->value = $shortcode;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'mpesa_shortcode';
        $d->value = $shortcode;
        $d->save();
    }

    $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_passkey')->find_one();
    if ($d) {
        $d->value = $passkey;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'mpesa_passkey';
        $d->value = $passkey;
        $d->save();
    }

    $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_env')->find_one();
    if ($d) {
        $d->value = $env;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'mpesa_env';
        $d->value = $env;
        $d->save();
    }

    _log('[' . $admin['username'] . ']: M-Pesa ' . Lang::T('Settings_Saved_Successfully'), 'Admin', $admin['id']);
    r2(U . 'paymentgateway/mpesa', 's', Lang::T('Settings_Saved_Successfully'));
}

function mpesa_get_access_token() {
    global $config;
    
    $url = ($config['mpesa_env'] == 'sandbox') 
        ? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
        : 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    $credentials = base64_encode($config['mpesa_consumer_key'] . ':' . $config['mpesa_consumer_secret']);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response);
    return $result->access_token;
}

function mpesa_create_transaction($trx, $user)
{
    global $config;

    $timestamp = date('YmdHis');
    $password = base64_encode($config['mpesa_shortcode'] . $config['mpesa_passkey'] . $timestamp);
    
    $access_token = mpesa_get_access_token();
    
    $url = ($config['mpesa_env'] == 'sandbox')
        ? 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
        : 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

    $curl_post_data = array(
        'BusinessShortCode' => $config['mpesa_shortcode'],
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $trx['price'],
        'PartyA' => $user['phonenumber'],
        'PartyB' => $config['mpesa_shortcode'],
        'PhoneNumber' => $user['phonenumber'],
        'CallBackURL' => U . 'https://f26d-102-219-210-201.ngrok-free.app/benfex-setup/benfex/system/paymentgateway/mpesa.php',
        'AccountReference' => $trx['id'],
        'TransactionDesc' => 'Payment for Order #' . $trx['id']
    );

    $data_string = json_encode($curl_post_data);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response);

    if (isset($result->ResponseCode) && $result->ResponseCode == "0") {
        $d = ORM::for_table('tbl_payment_gateway')
            ->where('username', $user['username'])
            ->where('status', 1)
            ->find_one();
        $d->gateway_trx_id = $result->CheckoutRequestID;
        $d->pg_request = $response;
        $d->expired_date = date('Y-m-d H:i:s', strtotime("+1 hour"));
        $d->save();
        
        r2(U . "order/view/" . $d['id'], 's', Lang::T("Payment request sent. Please check your phone to complete the transaction."));
    } else {
        sendTelegram("M-Pesa payment failed\n\n" . json_encode($result, JSON_PRETTY_PRINT));
        r2(U . 'order/package', 'e', Lang::T("Failed to create transaction."));
    }
}

function mpesa_payment_notification() {
    // Log the raw callback for debugging
    $raw_post = file_get_contents('php://input');
    _log("M-Pesa Callback Received: " . $raw_post);

    // Decode the JSON payload
    $callbackData = json_decode($raw_post);
    
    // Validate callback data
    if (!isset($callbackData->Body->stkCallback)) {
        _log("M-Pesa Invalid Callback Format");
        return;
    }

    $resultCode = $callbackData->Body->stkCallback->ResultCode;
    $resultDesc = $callbackData->Body->stkCallback->ResultDesc;
    $merchantRequestID = $callbackData->Body->stkCallback->MerchantRequestID;
    $checkoutRequestID = $callbackData->Body->stkCallback->CheckoutRequestID;

    // Find the transaction
    $trx = ORM::for_table('tbl_payment_gateway')
        ->where('gateway_trx_id', $checkoutRequestID)
        ->find_one();

    if (!$trx) {
        _log("M-Pesa Transaction not found: " . $checkoutRequestID);
        return;
    }

    // Log the status
    _log("M-Pesa Payment Status: " . $resultDesc);

    if ($resultCode == 0) {
        // Extract payment details from callback
        $payment = $callbackData->Body->stkCallback->CallbackMetadata->Item;
        $amount = null;
        $mpesaReceiptNumber = null;
        $transactionDate = null;
        $phoneNumber = null;

        foreach ($payment as $item) {
            switch ($item->Name) {
                case "Amount":
                    $amount = $item->Value;
                    break;
                case "MpesaReceiptNumber":
                    $mpesaReceiptNumber = $item->Value;
                    break;
                case "TransactionDate":
                    $transactionDate = $item->Value;
                    break;
                case "PhoneNumber":
                    $phoneNumber = $item->Value;
                    break;
            }
        }

        // Get user details
        $user = ORM::for_table('tbl_customers')
            ->where('username', $trx['username'])
            ->find_one();

        if (!$user) {
            _log("M-Pesa User not found: " . $trx['username']);
            return;
        }

        // Activate the package
        if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'M-Pesa')) {
            _log("M-Pesa Payment Successful, But Failed to activate Package");
            sendTelegram("M-Pesa Payment Successful, But Failed to activate Package\nUser: " . $user['username'] . "\nAmount: " . $amount);
            return;
        }

        // Update transaction record
        $trx->pg_paid_response = $raw_post;
        $trx->payment_method = 'M-Pesa';
        $trx->payment_channel = 'M-Pesa STK Push';
        $trx->paid_date = date('Y-m-d H:i:s');
        $trx->status = 2;  // Paid
        $trx->save();

        // Log success
        _log("M-Pesa Payment Successful: " . $mpesaReceiptNumber);
        sendTelegram("M-Pesa Payment Successful\nUser: " . $user['username'] . "\nAmount: " . $amount . "\nReceipt: " . $mpesaReceiptNumber);
    } else {
        // Handle failed transaction
        $trx->status = 3;  // Failed
        $trx->pg_paid_response = $raw_post;
        $trx->save();

        _log("M-Pesa Payment Failed: " . $resultDesc);
        sendTelegram("M-Pesa Payment Failed\nUser: " . $trx['username'] . "\nReason: " . $resultDesc);
    }

    // Always return a 200 OK response to M-Pesa
    header("HTTP/1.1 200 OK");
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
}

function mpesa_get_status($trx, $user)
{
    global $config;
    
    $access_token = mpesa_get_access_token();
    
    $url = ($config['mpesa_env'] == 'sandbox')
        ? 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query'
        : 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query';

    $timestamp = date('YmdHis');
    $password = base64_encode($config['mpesa_shortcode'] . $config['mpesa_passkey'] . $timestamp);

    $curl_post_data = array(
        'BusinessShortCode' => $config['mpesa_shortcode'],
        'Password' => $password,
        'Timestamp' => $timestamp,
        'CheckoutRequestID' => $trx['gateway_trx_id']
    );

    $data_string = json_encode($curl_post_data);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response);

    if ($trx['status'] == 2) {
        r2(U . "order/view/" . $trx['id'], 's', Lang::T("Transaction has been paid."));
    } else if ($trx['status'] == 3) {
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction failed or expired."));
    } else {
        r2(U . "order/view/" . $trx['id'], 'w', Lang::T("Transaction still unpaid."));
    }
}


// error_log("Received POST request: " . json_encode($_POST));
// echo json_encode(["status" => "success", "message" => "Request received"]);

// $data = json_decode(file_get_contents('php://input'), true);
// error_log("Received Data: " . print_r($data, true)); // Logs the received data to the server logs
