{*
* 2007-2022 PrestaShop
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
*  @copyright 2007-2022 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<script type="text/javascript" src="{$js_dir}jquery/jquery-1.11.0.min.js"></script>

<div id="zenkipay-container" class="payment_module">
    <div class="zenkipay-form-container" >    
        <div class="zenkipay-payment-errors"></div>                
    </div>
</div> 

<script type="text/javascript">    
    jQuery(document).ready(function() {
        var orderId = '{$zenki_order_id}';  
        var paymentSignature = '{$payment_signature}';

        var purchaseOptions = {            
            orderId,
            paymentSignature,
        };                                

        console.log('openModal', purchaseOptions);                                   
                                
        zenkipay.openModal(purchaseOptions, handleZenkipayEvents);                                  
    });    

    var handleZenkipayEvents = function (error, data) {            
        console.log('handleZenkipayEvents error => ', error);
        console.log('handleZenkipayEvents data => ', data);

        if (error) {                                
            var errorMsg = "{l s='An unexpected error occurred.' mod='zenkipay'}";
            jQuery('.zenkipay-payment-errors').fadeIn(1000);
            jQuery('.zenkipay-payment-errors').text(errorMsg).fadeIn(1000);    
            return;                                  
        }                    
                
        return;
    };
</script>