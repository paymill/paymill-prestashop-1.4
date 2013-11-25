<link rel="stylesheet" type="text/css" href="{$components}/paymill_styles.css" />
<script type="text/javascript">
    var PAYMILL_PUBLIC_KEY = '{$public_key}';
    var PAYMILL_IMAGE = '{$components}/images';
    var prefilled = new Array();
    var submitted = false;
</script>
<script type="text/javascript" src="https://bridge.paymill.com/"></script>
<script type="text/javascript">
    function validate() {
        debug("Paymill handler triggered");
        var result = true;
        $('.error').remove();
    {if $payment == 'creditcard'}
        if (!paymill.validateHolder($('#paymill-account-holder').val())) {
            $('#paymill-account-holder').after("<p class='error paymillerror'>{l s='Please enter the creditcardholders name.' mod='pigmbhpaymill'}</p>");
            result = false;
        }
        if (!paymill.validateCardNumber($('#paymill-card-number').val())) {
            $('#paymill-card-number').after("<p class='error paymillerror'>{l s='Please enter your creditcardnumber.' mod='pigmbhpaymill'}</p>");
            result = false;
        }
        if (paymill.cardType($('#paymill-card-number').val()).toLowerCase() === 'maestro' && (!$('#paymill-card-cvc').val() || $('#paymill-card-cvc').val() === "000")) {
            $('#paymill-card-cvc').val('000');
        } else if (!paymill.validateCvc($('#paymill-card-cvc').val())) {
            $('#paymill-card-cvc').after("<p class='error paymillerror'>{l s='Please enter your CVC-code(back of card).' mod='pigmbhpaymill'}</p>");
            result = false;
        }
        if (!paymill.validateExpiry($('#card-expiry-month').val(), $('#card-expiry-year').val())) {
            $('#card-expiry-year').after("<p class='error paymillerror'>{l s='Please enter a valid date.' mod='pigmbhpaymill'}</p>");
            result = false;
        }
    {elseif $payment == 'debit'}
        if (!paymill.validateHolder($('#paymill_accountholder').val())) {
            $('#paymill_accountholder').after("<p class='error paymillerror'>{l s='Please enter the accountholder' mod='pigmbhpaymill'}</p>");
            result = false;
        }
        {if $paymill_sepa === 'false'}
        if (!paymill.validateAccountNumber($('#paymill_accountnumber').val())) {
            $('#paymill_accountnumber').after("<p class='error paymillerror'>{l s='Please enter your accountnumber.' mod='pigmbhpaymill'}</p>");
            result = false;
        }
        if (!paymill.validateBankCode($('#paymill_banknumber').val())) {
            $('#paymill_banknumber').after("<p class='error paymillerror'>{l s='Please enter your bankcode.' mod='pigmbhpaymill'}</p>");
            result = false;
        }
        {else}
        if ($('#paymill_iban').val() === "") {
            $('#paymill_iban').after("<p class='error paymillerror'>{l s='Please enter your iban.' mod='pigmbhpaymill'}</p>");
            result = false;
        }
        if ($('#paymill_bic').val() === "") {
            $('#paymill_bic').after("<p class='error paymillerror'>{l s='Please enter your bic.' mod='pigmbhpaymill'}</p>");
            result = false;
        }
        {/if}
    {/if}
        if (!result) {
            $("#submitButton").removeAttr('disabled');
        } else {
            debug("Validations successful");
        }

        return result;
    }
    $(document).ready(function() {
        prefilled = getFormData(prefilled, true);
        $("#paymill_form").submit(function(event) {
            if (!submitted) {
                $("#submitButton").attr('disabled', true);
                var formdata = new Array();
                formdata = getFormData(formdata, false);

                if (prefilled.toString() === formdata.toString()) {
                    result = new Object();
                    result.token = 'dummyToken';
                    PaymillResponseHandler(null, result);
                } else {
                    if (validate()) {
                        try {
    {if $payment == 'creditcard'}
                            paymill.createToken({
                                number: $('#paymill-card-number').val(),
                                cardholder: $('#paymill-account-holder').val(),
                                exp_month: $('#card-expiry-month').val(),
                                exp_year: $('#card-expiry-year').val(),
                                cvc: $('#paymill-card-cvc').val(),
                                amount_int: {$total},
                                currency: '{$currency_iso}'
                            }, PaymillResponseHandler);
    {elseif $payment == 'debit'}
        {if $paymill_sepa === 'false'}
                            paymill.createToken({
                                number: $('#paymill_accountnumber').val(),
                                bank: $('#paymill_banknumber').val(),
                                accountholder: $('#paymill_accountholder').val()
                            }, PaymillResponseHandler);
        {else}
                            paymill.createToken({
                                iban: $('#paymill_iban').val(),
                                bic: $('#paymill_bic').val(),
                                accountholder: $('#paymill_accountholder').val()
                            }, PaymillResponseHandler);
        {/if}
    {/if}
                        } catch (e) {
                            alert("Ein Fehler ist aufgetreten: " + e);
                        }
                    }
                }
            }
            return submitted;
        });

        $('#paymill-card-number').keyup(function() {
            var brand = paymill.cardType($('#paymill-card-number').val());
            brand = brand.toLowerCase();
            $("#paymill-card-number")[0].className = $("#paymill-card-number")[0].className.replace(/paymill-paymill-card-number-.*/g, '');
            if (brand !== 'unknown') {
                if (brand === 'american express') {
                    brand = 'amex';
                }
                $('#paymill-card-number').addClass("paymill-paymill-card-number-" + brand);
            }
        });
        $('#paymill-card-expirydate').keyup(function() {
            var expiryDate = $("#paymill-card-expirydate").val();
            if (expiryDate.match(/^.{2}$/)) {
                expiryDate += "/";
                $("#paymill-card-expirydate").val(expiryDate);
            }
        });

    {if $paymill_sepa}
        $('#paymill_iban').keyup(function() {
            var iban = $('#paymill_iban').val();
            if (!iban.match(/^DE/)) {
                var newVal = "DE";
                if (iban.match(/^.{2}(.*)/)) {
                    newVal += iban.match(/^.{2}(.*)/)[1];
                }
                $('#paymill_iban').val(newVal);
            }
        });
        $('#paymill_iban').trigger('keyup');
    {/if}
    });
    function getFormData(array, ignoreEmptyValues) {
        $('#paymill_form :input').not(':hidden').each(function() {
            if ($(this).val() === "" && ignoreEmptyValues) {
                return;
            }
            array.push($(this).val());
        });
        return array;
    }
    function PaymillResponseHandler(error, result) {
        debug("Started Paymill response handler");
        if (error) {
            $("#submitButton").removeAttr('disabled');
            debug("API returned error:" + error.apierror);
            alert("API returned error:" + error.apierror);
            submitted = false;
        } else {
            debug("Received token from Paymill API: " + result.token);
            var form = $("#paymill_form");
            var token = result.token;
            submitted = true;
            form.append("<input type='hidden' name='paymillToken' value='" + token + "'/>");
            form.submit();
        }
    }
    function debug(message) {
    {if $paymill_debugging == 'true'}
        {if $payment == 'creditcard'}
        console.log('[PaymillCC] ' + message);
        {elseif $payment == 'debit'}
        console.log('[PaymillELV] ' + message);
        {/if}
    {/if}
    }

</script>

{capture name=path}{l s='Paymill' mod='pigmbhpaymill'}{/capture}
{include file="$tpl_dir./breadcrumb.tpl"}

<h2>{l s='Order summary' mod='pigmbhpaymill'}</h2>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if $nbProducts <= 0}
    <p class="warning">{l s='Your cart is empty.' mod='pigmbhpaymill'}</p>
{else}

    <form id='paymill_form' action="{$this_path_ssl}controllers/front/validation.php" method="post">
        <div class="debit">
            {if $payment == "creditcard"}
                <input type="hidden" name="payment" value="creditcard">
                <fieldset>
                    <label for="paymill-card-number" class="field-left">{l s='Creditcard-number' mod='pigmbhpaymill'}*</label>
                    <input id="paymill-card-number" type="text" class="field-left" value="{if $prefilledFormData.last4}****************{$prefilledFormData.last4}{/if}" />
                    <label for="paymill-card-expirydate" class="field-right">{l s='Valid until' mod='pigmbhpaymill'}*</label><br>
                    <input id="paymill-card-expirydate" type="text" class="field-right">
                </fieldset>
                <fieldset>
                    <label for="paymill-card-holder" class="field-left">{l s='Cardholder' mod='pigmbhpaymill'}*</label>
                    <input id="paymill-card-holder" type="text" class="field-left" value="{if $prefilledFormData.card_holder}{$prefilledFormData.card_holder}{else}{$customer}{/if}"/>
                    <label for="paymill-card-cvc" class="field-right">{l s='CVC' mod='pigmbhpaymill'}*<span class="paymill-tooltip" title="{l s='What is a CVV/CVC number? Prospective credit cards will have a 3 to 4-digit number, usually on the back of the card. It ascertains that the payment is carried out by the credit card holder and the card account is legitimate. On Visa the CVV (Card Verification Value) appears after and to the right of your card number. Same goes for Mastercard’s CVC (Card Verfication Code), which also appears after and to the right of  your card number, and has 3-digits. Diners Club, Discover, and JCB credit and debit cards have a three-digit card security code which also appears after and to the right of your card number. The American Express CID (Card Identification Number) is a 4-digit number printed on the front of your card. It appears above and to the right of your card number. On Maestro the CVV appears after and to the right of your number. If you don’t have a CVV for your Maestro card you can use 000.' mod='pigmbhpaymill'}">?</span></label>
                    <input id="paymill-card-cvc" type="text" class="field-right" value="{if $prefilledFormData.last4}***{/if}" />
                </fieldset>
            {elseif $payment == "debit"}
                <input type="hidden" name="payment" value="debit">
                <fieldset>
                    {if !$paymill_sepa}
                        <label for="paymill_accountnumber" class="field-left">{l s='Accountnumber' mod='pigmbhpaymill'}*</label>
                        <input id="paymill_accountnumber" type="text" class="field-left" value="{if $prefilledFormData.account}{$prefilledFormData.account}{/if}" />
                        <label for="paymill_banknumber" class="field-right">{l s='Banknumber' mod='pigmbhpaymill'}*</label>
                        <input id="paymill_banknumber" type="text" class="field-right" value="{if $prefilledFormData.code}{$prefilledFormData.code}{/if}" />
                    {else}
                        <label for="paymill_iban" class="field-left">IBAN*</label>
                        <input id="paymill_iban" type="text" class="field-left" value="{if $prefilledFormData.iban}{$prefilledFormData.iban}{/if}" />
                        <label for="paymill_bic" class="field-right">BIC*</label>
                        <input id="paymill_bic" type="text" class="field-right" value="{if $prefilledFormData.bic}{$prefilledFormData.bic}{/if}" />
                    {/if}
                </fieldset>
                <fieldset>
                    <label for="paymill_accountholder" class="field-full">{l s='Accountholder' mod='pigmbhpaymill'}*</label>
                    <input id="paymill_accountholder" type="text" class="field-full" value="{if $prefilledFormData.holder}{$prefilledFormData.holder}{else}{$customer}{/if}"/>
                </fieldset>
            {/if}
            <p class="description">
                {l s='The following Amount will be charged' mod='pigmbhpaymill'}: <b>{displayPrice price=$displayTotal}</b><br>
                {l s='Fields marked with a * are required' mod='pigmbhpaymill'}
            </p>
        </div>
        <p class="cart_navigation">
            {if $opc}
                <a href="{$link->getPageLink('order.php', true)}?step=3" class="button_large">{l s='Payment selection' mod='pigmbhpaymill'}</a>
            {/if}
            {if !$opc}
                <a href="{$link->getPageLink('order.php', true)}?step=3" class="button_large">{l s='Payment selection' mod='pigmbhpaymill'}</a>
            {/if}
            <input type="submit" id='submitButton' value="{l s='Order' mod='pigmbhpaymill'}" class="exclusive_large" />
        </p>
    </form>
{/if}