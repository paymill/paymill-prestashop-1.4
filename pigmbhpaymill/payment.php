<?php

/**
 * PaymentController
 *
 * @category   PayIntelligent
 * @copyright  Copyright (c) 2013 PayIntelligent GmbH (http://payintelligent.de)
 */
include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../header.php');

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
$fastCheckout = false;
if (Configuration::get('PIGMBH_PAYMILL_FASTCHECKOUT')) {
    if (Tools::getValue('payment') == 'creditcard') {
        $sql = 'SELECT `clientId`,`paymentId` FROM `pigmbh_paymill_creditcard_userdata` WHERE `userId`=' . $cart->id_customer;
    } elseif (Tools::getValue('payment') == 'debit') {
        $sql = 'SELECT `clientId`,`paymentId` FROM `pigmbh_paymill_directdebit_userdata` WHERE `userId`=' . $cart->id_customer;
    }
    try{
        $dbData = $db->getRow($sql);
    }catch(Exception $exception){
        $dbData = false;
    }

    if ($dbData != false && count($dbData) > 0) {
        $fastCheckout = true;
    }
}

$customers = CustomerCore::getCustomers();
$currency = Currency::getCurrency((int) $cart->id_currency);
$_SESSION['pigmbhPaymill']['authorizedAmount'] = intval($cart->getOrderTotal(true, Cart::BOTH) * 100);

foreach($customers as $customer){
    if($customer['id_customer'] == $cart->id_customer){
        $customername = $customer['firstname'] . ' ' . $customer['lastname'];
        break;
    }
}
$data = array(
    'nbProducts' => $cart->nbProducts(),
    'cust_currency' => $cart->id_currency,
    'currency_iso' => $currency['iso_code'],
    'total' => $_SESSION['pigmbhPaymill']['authorizedAmount'],
    'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/pigmbhpaymill/',
    'public_key' => Configuration::get('PIGMBH_PAYMILL_PUBLICKEY'),
    'bridge_url' => Configuration::get('PIGMBH_PAYMILL_BRIDGEURL'),
    'payment' => Tools::getValue('payment'),
    'paymill_show_label' => Configuration::get('PIGMBH_PAYMILL_LABEL') == 'on',
    'paymill_debugging' => Configuration::get('PIGMBH_PAYMILL_DEBUG') == 'on',
    'components' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'modules/pigmbhpaymill/components/',
    'customer' => $customername
);

$smarty->assign($data);
if ($fastCheckout) {
    echo Module::display('pigmbhpaymill', 'views/templates/front/fastCheckout.tpl');
} else {
    echo Module::display('pigmbhpaymill', 'views/templates/front/paymentForm.tpl');
}