<?php
/**
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
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class ZenkipayValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        /*
         * If the module is not active anymore, no need to process anything.
         */
        if ($this->module->active == false) {
            exit;
        }

        $zenki_order_id = Tools::getValue('zenki_order_id');
        $cart = $this->context->cart;
        $customer = new Customer((int) $cart->id_customer);
        $secure_key = Context::getContext()->customer->secure_key;
        $module_name = $this->module->displayName;
        $currency_id = (int) Context::getContext()->currency->id;
        $amount = 0; // When the payment is pending or error, the value should be 0.

        if ($this->isValidOrder() === true) {
            $payment_status = Configuration::get('PS_OS_ZENKIPAY_PAYMENT');
            $message = $this->module->l('Mode:') . ' ' . (Configuration::get('ZENKIPAY_MODE') ? $this->module->l('Live') : $this->module->l('Test'));
        } else {
            $payment_status = Configuration::get('PS_OS_ERROR');
            $message = $this->module->l('An error occurred while processing payment');
        }

        $this->module->validateOrder($cart->id, $payment_status, $amount, $module_name, $message, [], $currency_id, false, $secure_key);

        /**
         * If the order has been validated we try to retrieve it
         */
        $order_id = Order::getOrderByCartId((int) $cart->id);
        if ($order_id && $secure_key == $customer->secure_key) {
            /**
             * The order has been placed so we redirect the customer on the confirmation page.
             */
            $module_id = $this->module->id;
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $module_id . '&id_order=' . $order_id . '&key=' . $secure_key);
        } else {
            /*
             * An error occured and is shown on a new page.
             */
            // $this->errors[] = $this->module->l('An error occurred. Please contact the merchant to have more information');
            $this->context->cookie->__set('zenkipay_error', 'An error occurred. Please contact the merchant to have more information.');
            Tools::redirect('index.php?controller=order&step=1');
        }
    }

    protected function isValidOrder()
    {
        return true;
    }
}
