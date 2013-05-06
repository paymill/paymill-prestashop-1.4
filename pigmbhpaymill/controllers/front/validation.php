<?php

/**
 * validation
 *
 * @category   PayIntelligent
 * @copyright  Copyright (c) 2013 PayIntelligent GmbH (http://payintelligent.de)
 */
include(dirname(__FILE__) . '/../../../../config/config.inc.php');
include(dirname(__FILE__) . '/../../../../header.php');
include(dirname(__FILE__) . '/../../pigmbhpaymill.php');

session_start();
$db = Db::getInstance();
$token = Tools::getValue('paymillToken');
$payment = Tools::getValue('payment');
$validPayments = array();
if (Configuration::get('PIGMBH_PAYMILL_DEBIT')) {
    $validPayments[] = 'debit';
}
if (Configuration::get('PIGMBH_PAYMILL_CREDITCARD')) {
    $validPayments[] = 'creditcard';
}

if (empty($token)) {
    paymillLog('No paymill token was provided. Redirect to payments page.');
    Tools::redirectLink(__PS_BASE_URI__ . 'order.php?step=1');
} elseif (!in_array($payment, $validPayments)) {
    paymillLog('The selected Paymentmethod is not valid.(' . $payment . ')');
    Tools::redirectLink(__PS_BASE_URI__ . 'order.php?step=1');
}

paymillLog('Start processing payment with token ' . $token);
$api_url = Configuration::get('PIGMBH_PAYMILL_APIURL');
$private_key = Configuration::get('PIGMBH_PAYMILL_PRIVATEKEY');
$libBase = dirname(__FILE__) . '/../../paymill/v2/lib/';

$currency = Currency::getCurrency((int) $cart->id_currency);
$currency = $currency['iso_code'];
foreach (CustomerCore::getCustomers() as $customer) {
    if ($customer['id_customer'] == $cart->id_customer) {
        $customername = $customer['lastname'] . ', ' . $customer['firstname'];
        $customermail = $customer['email'];
        break;
    }
}
$metadata = Configuration::get('PS_SHOP_NAME');
$processData = array(
    'authorizedAmount' => $_SESSION['pigmbhPaymill']['authorizedAmount'],
    'token' => $token,
    'amount' => (int) ($cart->getOrderTotal(true, Cart::BOTH) * 100),
    'currency' => $currency,
    'name' => $customername,
    'email' => $customermail,
    'description' => $metadata['description'] . ' ' . $customermail,
    'libBase' => $libBase,
    'privateKey' => $private_key,
    'apiUrl' => $api_url,
    'userId' => $cart->id_customer
);

if (Configuration::get('PIGMBH_PAYMILL_FASTCHECKOUT')) {
    if ($payment == 'creditcard') {
        $sql = 'SELECT `clientId`,`paymentId` FROM `pigmbh_paymill_creditcard_userdata` WHERE `userId`=' . $cart->id_customer;
    } elseif ($payment == 'debit') {
        $sql = 'SELECT `clientId`,`paymentId` FROM `pigmbh_paymill_directdebit_userdata` WHERE `userId`=' . $cart->id_customer;
    }
    try {
        $userData = $db->getRow($sql);
    } catch (Exception $exception) {
        $userData = false;
    }

    if (!empty($userData['clientId']) && !empty($userData['paymentId'])) {
        $processData['clientId'] = $userData['clientId'];
        $processData['paymentId'] = $userData['paymentId'];
    }
}

// process the payment

$paymill = new PigmbhPaymill();
$user = new Customer((int) $cart->id_customer);

$result = processPayment($processData);

paymillLog(
        'Payment processing resulted in: '
        . ($result ? 'Success' : 'Fail')
);
// finish the order if payment was sucessfully processed
if ($result === true) {
    paymillLog('Finish order.');
    $paymill->validateOrder(
            (int) $cart->id, Configuration::get('PS_OS_PREPARATION'), $cart->getOrderTotal(true, Cart::BOTH), $paymill->displayName, null, array(), null, false, $user->secure_key);
    Tools::redirectLink(__PS_BASE_URI__ . 'order-confirmation.php?key=' . $user->secure_key . '&id_cart=' . (int) $cart->id . '&id_module=' . (int) $paymill->id . '&id_order=' . (int) $paymill->currentOrder);
} else {
    Tools::redirectLink(__PS_BASE_URI__ . 'order.php?step=1');
}

/**
 * Processes the payment against the paymill API
 * @param $params array The settings array
 * @return boolean
 */
function processPayment($params)
{
    paymillLog('Parameters: ' . var_export($params, true));
// reformat paramters
    $params['currency'] = strtolower($params['currency']);
// setup client params
    $client_params = array(
        'email' => $params['email'],
        'description' => $params['name']
    );
// setup credit card params
    $payment_params = array(
        'token' => $params['token']
    );
// setup transaction params
    $transactionParams = array(
        'amount' => $params['authorizedAmount'],
        'currency' => $params['currency'],
        'description' => $params['description']
    );
    require_once $params['libBase'] . 'Services/Paymill/Transactions.php';
    require_once $params['libBase'] . 'Services/Paymill/Clients.php';
    require_once $params['libBase'] . 'Services/Paymill/Payments.php';

    $clientsObject = new Services_Paymill_Clients(
                    $params['privateKey'], $params['apiUrl']
    );
    $transactionsObject = new Services_Paymill_Transactions(
                    $params['privateKey'], $params['apiUrl']
    );

    $paymentObject = new Services_Paymill_Payments(
                    $params['privateKey'], $params['apiUrl']
    );
// perform conection to the Paymill API and trigger the payment
    try {
        if (!array_key_exists('paymentId', $params)) {
            $payment = $paymentObject->create($payment_params);
            if (!isset($payment['id'])) {
                paymillLog('No Payment created: ' . var_export($payment, true));
                return false;
            } else {
                paymillLog('Payment created: ' . $payment['id']);
            }
        } else {
            $payment['id'] = $params['paymentId'];
            paymillLog('Saved payment used: ' . $params['paymentId']);
        }


        if (!array_key_exists('clientId', $params)) {
// create client
            $client_params['creditcard'] = $payment['id'];
            $client = $clientsObject->create($client_params);
            if (!isset($client['id'])) {
                paymillLog('No client created: ' . var_export($client, true));
                return false;
            } else {
                paymillLog('Client created: ' . $client['id']);
            }
        } else {
            $client['id'] = $params['clientId'];
            paymillLog('Saved client used: ' . $params['clientId']);
        }

// create transaction
        $transactionParams['client'] = $client['id'];
        $transactionParams['payment'] = $payment['id'];
        $transaction = $transactionsObject->create($transactionParams);
        if (!confirmTransaction($transaction)) {
            return false;
        }
        if (!array_key_exists('clientId', $params) && !array_key_exists('paymentId', $params)) {
            saveUserData($client['id'], $payment['id'], $params['userId']);
        }
        if ($params['authorizedAmount'] !== $params['amount']) {
            if ($params['authorizedAmount'] > $params['amount']) {
                require_once $params['libBase'] . 'Services/Paymill/Refunds.php';
// basketamount is lower than the authorized amount
                $refundObject = new Services_Paymill_Refunds(
                                $params['privateKey'], $params['apiUrl']
                );
                $refundTransaction = $refundObject->create(
                        array(
                            'transactionId' => $transaction['id'],
                            'params' => array(
                                'amount' => $params['authorizedAmount'] - $params['amount']
                            )
                        )
                );
                if (isset($refundTransaction['data']['response_code']) && $refundTransaction['data']['response_code'] !== 20000) {
                    paymillLog("An Error occured: " . var_export($refundTransaction, true));
                    return false;
                }
                if (!isset($refundTransaction['data']['id'])) {
                    paymillLog("No Refund created" . var_export($refundTransaction, true));
                    return false;
                } else {
                    paymillLog("Refund created: " . $refundTransaction['data']['id']);
                }
            } else {
// basketamount is higher than the authorized amount (paymentfee etc.)
                $secoundTransactionParams = array(
                    'amount' => $params['amount'] - $params['authorizedAmount'],
                    'currency' => $params['currency'],
                    'description' => $params['description']
                );
                $secoundTransactionParams['client'] = $client['id'];
                $secoundTransactionParams['payment'] = $payment['id'];
                if (!confirmTransaction($transactionsObject->create($secoundTransactionParams))) {
                    return false;
                }
            }
        }
        return true;
    } catch (Services_Paymill_Exception $ex) {
// paymill wrapper threw an exception
        paymillLog('Exception thrown from paymill wrapper: ' . $ex->getMessage());
        return false;
    }
    return true;
}

function paymillLog($message)
{
    $logging = Configuration::get('PIGMBH_PAYMILL_LOGGING');
    $log_file = dirname(__FILE__) . '/../../log.txt';
    if (is_writable($log_file) && $logging == 'on') {
        $handle = fopen($log_file, 'a'); //
        fwrite($handle, '[' . date(DATE_RFC822) . '] ' . $message . "\n");
        fclose($handle);
    }
}

function confirmTransaction($transaction)
{
    if (isset($transaction['data']['response_code'])) {
        paymillLog("An Error occured: " . var_export($transaction, true));
        return false;
    }
    if (!isset($transaction['id'])) {
        paymillLog("No transaction created: " . var_export($transaction, true));
        return false;
    } else {
        paymillLog("Transaction created: " . $transaction['id']);
    }

// check result
    if (is_array($transaction) && array_key_exists('status', $transaction)) {
        if ($transaction['status'] == "open") {
// transaction was issued but status is open for any reason
            paymillLog("Status is open.");
            return false;
        } elseif ($transaction['status'] != "closed") {
// another error occured
            paymillLog("Unknown error." . var_export($transaction, true));
            return false;
        }
    } else {
// another error occured
        paymillLog("Transaction could not be issued.");
        return false;
    }
    return true;
}

function saveUserData($clientId, $paymentId, $userId)
{
    if (Configuration::get('PIGMBH_PAYMILL_FASTCHECKOUT')) {
        $db = Db::getInstance();
        $payment = Tools::getValue('payment');
        if ($payment == 'creditcard') {
            $table = 'pigmbh_paymill_creditcard_userdata';
        } elseif ($payment == 'debit') {
            $table = 'pigmbh_paymill_directdebit_userdata';
        }
        $sql = "REPLACE INTO `$table` (`clientId`, `paymentId`, `userId`) VALUES('$clientId', '$paymentId', $userId)";
        try {
            $result = $db->execute($sql);
            if($result){
                paymillLog("UserData saved." . "VALUES('$clientId', '$paymentId', $userId);");
            }
        } catch (Exception $exception) {
            paymillLog("Failed saving UserData. " . $exception->getMessage());
        }
    }
}