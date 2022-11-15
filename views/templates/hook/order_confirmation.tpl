{*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
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
*  @copyright 2007-2015 PrestaShop SA
*  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
        var orderId = {$order_id|@json_encode nofilter};  
        var paymentSignature = {$payment_signature|@json_encode nofilter};  

        var purchaseOptions = {            
            orderId,
            paymentSignature,
        };                                

        console.log('openModal', purchaseOptions)                                    
                                
        zenkiPay.openModal(purchaseOptions, handleZenkipayEvents);                                  
    });    


    var handleZenkipayEvents = function (error, data, details) {            
        if (!error && details.postMsgType === 'done') {            
            return;
        }

        if (error && details.postMsgType === 'error') {                                
            var errorMsg = "{l s='An unexpected error occurred.' mod='zenkipay'}";
            jQuery('.zenkipay-payment-errors').fadeIn(1000);
            jQuery('.zenkipay-payment-errors').text(errorMsg).fadeIn(1000);    
            return;                                  
        }                
                
        return
    };
</script>