<?php
/**
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
 */
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

        Logger::addLog('Zenkipay - Validation', 1, null, 'Cart', Context::getContext()->cart->id, true);

        $cart = Context::getContext()->cart;
        $customer = new Customer((int) $cart->id_customer);
        $module_name = $this->module->displayName;
        $amount = $cart->getOrderTotal();
        $currency_id = (int) Context::getContext()->currency->id;
        $currency_iso_code = (int) Context::getContext()->currency->iso_code;
        $secure_key = Context::getContext()->customer->secure_key;

        if ($this->isValidOrder() === true) {
            $payment_status = Configuration::get('PS_OS_ZENKIPAY_PAYMENT');
            $message =
                $this->module->l('Transaction Details:') .
                "\n\n" .
                $this->module->l('Payment method:') .
                ' ' .
                $module_name .
                "\n" .
                $this->module->l('Amount:') .
                ' $' .
                number_format($amount, 2) .
                ' ' .
                Tools::strtoupper($currency_iso_code) .
                "\n" .
                $this->module->l('Status:') .
                ' ' .
                $this->module->l('Pending') .
                "\n" .
                $this->l('Processed on:') .
                ' ' .
                date('Y-m-d H:i:s') .
                "\n" .
                $this->module->l('Mode:') .
                ' ' .
                (Configuration::get('ZENKIPAY_MODE') ? $this->module->l('Live') : $this->module->l('Test')) .
                "\n";
        } else {
            $payment_status = Configuration::get('PS_OS_ERROR');

            /**
             * Add a message to explain why the order has not been validated
             */
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
            $this->errors[] = $this->module->l('An error occured. Please contact the merchant to have more informations');
            return $this->setTemplate('error.tpl');
        }
    }

    protected function isValidOrder()
    {
        /*
         * Add your checks right there
         */
        return true;
    }
}
