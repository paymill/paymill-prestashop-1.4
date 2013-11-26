<?php

/**
 * PaymentController
 *
 * @category   PayIntelligent
 * @copyright  Copyright (c) 2013 PayIntelligent GmbH (http://payintelligent.de)
 */
include(dirname(__FILE__) . '/../../config/config.inc.php');
include(dirname(__FILE__) . '/../../header.php');
require_once dirname(__FILE__) . '/paymill/v2/lib/Services/Paymill/Clients.php';
require_once dirname(__FILE__) . '/paymill/v2/lib/Services/Paymill/Payments.php';


global $cart, $smarty;
$db = Db::getInstance();
session_start();
$validPayments = array();

if (Configuration::get('PIGMBH_PAYMILL_DEBIT')) {
    $validPayments[] = 'debit';
}
if (Configuration::get('PIGMBH_PAYMILL_CREDITCARD')) {
    $validPayments[] = 'creditcard';
}

if (!in_array(Tools::getValue('payment'), $validPayments)) {
    Tools::redirectLink(__PS_BASE_URI__ . 'order.php?step=1');
}

foreach (CustomerCore::getCustomers() as $currentCustomer) {
    if ($currentCustomer['id_customer'] == $cart->id_customer) {
        $customer = $currentCustomer;
        break;
    }
}


if (Configuration::get('PIGMBH_PAYMILL_FASTCHECKOUT')) {
    if (Tools::getValue('payment') == 'creditcard') {
        $sql = 'SELECT `clientId`,`paymentId` FROM `pigmbh_paymill_creditcard_userdata` WHERE `userId`=' . $cart->id_customer;
    } elseif (Tools::getValue('payment') == 'debit') {
        $sql = 'SELECT `clientId`,`paymentId` FROM `pigmbh_paymill_directdebit_userdata` WHERE `userId`=' . $cart->id_customer;
    }
    try {
        $dbData = $db->getRow($sql);
    } catch (Exception $exception) {
        $dbData = false;
    }
}
if (isset($dbData['clientId'])) {
    $clientObject = new Services_Paymill_Clients(Configuration::get('PIGMBH_PAYMILL_PRIVATEKEY'), "https://api.paymill.com/v2/");
    $oldClient = $clientObject->getOne($dbData['clientId']);
    if ($customer["email"] !== $oldClient['email']) {
        $clientObject->update(array(
            'id' => $dbData['clientId'],
            'email' => $customer["email"]
            )
        );
    }
}

$payment = false;
if (isset($dbData['paymentId'])) {
    $paymentObject = new Services_Paymill_Payments(Configuration::get('PIGMBH_PAYMILL_PRIVATEKEY'), "https://api.paymill.com/v2/");
    $paymentResponse = $paymentObject->getOne($dbData['paymentId']);
    if ($paymentResponse['id'] === $dbData['paymentId']) {
        $payment = $dbData['paymentId'] !== '' ? $paymentResponse : false;
    }
    $payment['expire_date'] = null;
    if (isset($payment['expire_month'])) {
        $payment['expire_month'] = $payment['expire_month'] <= 9 ? '0' . $payment['expire_month'] : $payment['expire_month'];
        $payment['expire_date'] = $payment['expire_month'] . "/" . $payment['expire_year'];
    }
}
$currency = Currency::getCurrency((int) $cart->id_currency);
$_SESSION['pigmbhPaymill']['authorizedAmount'] = (int) round($cart->getOrderTotal(true, Cart::BOTH) * 100);


$data = array(
    'nbProducts' => $cart->nbProducts(),
    'cust_currency' => $cart->id_currency,
    'currency_iso' => $currency['iso_code'],
    'total' => $_SESSION['pigmbhPaymill']['authorizedAmount'],
    'displayTotal' => $cart->getOrderTotal(true, Cart::BOTH),
    'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/pigmbhpaymill/',
    'public_key' => Configuration::get('PIGMBH_PAYMILL_PUBLICKEY'),
    'paymill_sepa' => Configuration::get('PIGMBH_PAYMILL_SEPA') == 'on',
    'payment' => Tools::getValue('payment'),
    'paymill_debugging' => Configuration::get('PIGMBH_PAYMILL_DEBUG') == 'on',
    'components' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'modules/pigmbhpaymill/components/',
    'customer' => $customer['firstname'] . " " . $customer['lastname'],
    'prefilledFormData' => $payment,
);

$smarty->assign($data);
echo Module::display('pigmbhpaymill', 'views/templates/front/paymentForm.tpl');

include(dirname(__FILE__) . '/../../footer.php');