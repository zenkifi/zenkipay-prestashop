{*
* 2007-2015 PrestaShop
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
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<div id="zenkipay-container" class="payment_module">
    <div class="zenkipay-form-container" >    
        <img src="{$module_dir|escape:'htmlall':'UTF-8'}views/img/logo.png" alt="Zenkipay" class="zenkipay-logo"/>                   
        <form action="{$action}" id="zenkipay-payment-form" method="post" class="zenkipay-payment-form">              
            <h3 class="zenkipay_title mb10">{l s='Pay with cryptos… any wallet, any coin!. Transaction 100% secured.' mod='zenkipay'}</h3>
            <p>{l s='Zenkipay´s latest, most complete cryptocurrency payment processing solution. Accept any crypto coin with over 150 wallets around the world.' mod='zenkipay'}</p>
            
            <div class="zenkipay-payment-errors" style="display: {if isset($zenkipay_error)}block{else}none{/if};">
                {if isset($zenkipay_error)}{$zenkipay_error|escape:'htmlall':'UTF-8'}{/if}
            </div>                
        </form>
    </div>
</div>    
    
<script type="text/javascript">    
    $(document).ready(function() {                     
        var zenkipayKey = "{$pk|escape:'htmlall':'UTF-8'}";
        var purchaseSignature = "{$signature|escape:'htmlall':'UTF-8'}";
        var purchaseData = {$purchase_data|@json_encode nofilter};    

        var purchaseOptions = {            
            zenkipayKey,          
            purchaseData,  
            purchaseSignature,
            style: {
                shape: 'square',
                theme: 'light',
            },
        };               
        
        $("#payment-confirmation > .ps-shown-by-js > button").click(function(event) {                   
            var myPaymentMethodSelected = $(".payment-options").find("input[data-module-name='zenkipay']").is(":checked");            
            if (myPaymentMethodSelected){
                event.preventDefault();                                      
                
                $(this).prop('disabled', true); /* Disable the submit button to prevent repeated clicks */
                $('.zenkipay-payment-errors').hide();     

                console.log('purchaseOptions', purchaseOptions)                                                   
                                
                zenkiPay.openModal(purchaseOptions, handleZenkipayEvents);                    
            }
        });        
    });    


    var handleZenkipayEvents = function (error, data, details) {            
        var submitBtn = $("#payment-confirmation > .ps-shown-by-js > button");

        if (!error && details.postMsgType === 'done') {
            var zenkipayOrderId = data.orderId;
            $('#zenkipay-payment-form').append('<input type="hidden" name="zenkipay_trx_id" value="' + escape(zenkipayOrderId) + '" />');
            $('#zenkipay-payment-form').get(0).submit();            
        }

        if (error && details.postMsgType === 'error') {                                
            var errorMsg = "{l s='An unexpected error occurred.' mod='zenkipay'}";
            $('.zenkipay-payment-errors').fadeIn(1000);
            $('.zenkipay-payment-errors').text(errorMsg).fadeIn(1000);    
            return;                                  
        }                

        submitBtn.prop('disabled', false);  
        
        return false;
    };
</script>