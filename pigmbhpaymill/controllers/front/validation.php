<?php

/**
 * validation
 *
 * @category   PayIntelligent
 * @copyright  Copyright (c) 2013 PayIntelligent GmbH (http://payintelligent.de)
 */
include_once(dirname(__FILE__) . '/../../../../config/config.inc.php');
include(dirname(__FILE__) . '/../../../../header.php');
include(dirname(__FILE__) . '/../../validation.php');
$paymentController = new PigmbhpaymillValidationModuleFrontController();
global $cart;
foreach (CustomerCore::getCustomers() as $customer) {
    if ($customer['id_customer'] == $cart->id_customer) {
        $currentUser = $customer;
        break;
    }
}
$paymentController->initContent($cart, $currentUser, Configuration::get('PS_SHOP_NAME'));
include(dirname(__FILE__) . '/../../../../footer.php');