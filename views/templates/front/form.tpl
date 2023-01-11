{*
* 2007-2023 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2023 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<div id="zenkipay-container" class="payment_module">
    <div class="zenkipay-form-container">
        <img src="{$crypto_btn|escape:'htmlall':'UTF-8'}" alt="Zenkipay" class="zenkipay-crypto-btn" />
        <form action="{$action|escape:'htmlall':'UTF-8'}" id="zenkipay-payment-form" method="post"
            class="zenkipay-payment-form">
            <div class="zenkipay-payment-errors" style="display: {if isset($zenkipay_error)}block{else}none{/if};">
                {if isset($zenkipay_error)}{$zenkipay_error|escape:'htmlall':'UTF-8'}{/if}
            </div>
        </form>
    </div>
</div>


<script type="text/javascript">
    $(document).ready(function() {
        var previousMsgType = '';
        var $form = $('#zenkipay-payment-form');
        var $button = $("#payment-confirmation > .ps-shown-by-js > button");
        var createOrderUrl = "{$create_order_ajax|escape:'htmlall':'UTF-8'}";  
        var cartId = "{$cart_id|escape:'htmlall':'UTF-8'}";          

        $button.click(function(event) {
            var myPaymentMethodSelected = $(".payment-options").find(
                "input[data-module-name='zenkipay']").is(":checked");
            if (myPaymentMethodSelected) {
                event.preventDefault();

                /* Disable the submit button to prevent repeated clicks */
                $(this).prop('disabled', true);
                $('.zenkipay-payment-errors').hide();

                if ($form.find('[name=zenki_order_id]').length) {
                    $form.find('[name=zenki_order_id]').remove();
                }

                zenkipayOrderRequest();

                return false;
            }
        });

        function zenkipayOrderRequest() {
            $.post(createOrderUrl, { cart_id: cartId }).success((result) => {
                var response = JSON.parse(result);

                if (!response.hasOwnProperty('error')) {
                    var purchaseOptions = {
                        paymentSignature: response.payment_signature,
                        orderId: response.zenki_order_id,
                    };

                    $form.append('<input type="hidden" name="zenki_order_id" value="' + response
                        .zenki_order_id + '" />');

                    formHandler(purchaseOptions);
                } else {
                    handleError(response.message);
                }
            });
        }

        function formHandler(purchaseOptions) {
            console.log('purchaseOptions', purchaseOptions);
            zenkipay.openModal(purchaseOptions, handleZenkipayEvents);
        }

        function handleZenkipayEvents(error, data) {
            console.log('handleZenkipayEvents error => ', error);
            console.log('handleZenkipayEvents data => ', data);
            console.log('handleZenkipayEvents previousMsgType => ', previousMsgType);

            if (error) {
                handleError(error);
                return;
            }

            if (data.postMsgType === 'cancel' && data.isCompleted) {
                $button.prop('disabled', false);
                return;
            }

            if (data.postMsgType === 'shopper_payment_confirmation') {
                $form.submit();
                return;
            }

            if (data.postMsgType === 'done') {
                $form.submit();
                return;
            }

            if ((previousMsgType === 'processing_payment' || previousMsgType === 'done') && data.isCompleted) {
                $form.submit();
            }

            previousMsgType = data.postMsgType;
            return;
        }

        function handleError(error) {
            $button.prop('disabled', false);
            $('.zenkipay-payment-errors').fadeIn(1000);
            $('.zenkipay-payment-errors').text('ERROR ' + error).fadeIn(1000);
            zenkipay.closeModal();
        }
    });
</script>