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

class ZenkipayPaymentModuleFrontController extends ModuleFrontController
{
    public function __construct()
    {
        $this->auth = false;
        parent::__construct();
        $this->context = Context::getContext();
        include_once $this->module->getLocalPath() . 'zenkipay.php';
    }

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;
        parent::initContent();
        if (Tools::getValue('process') == 'validation') {
            $this->validation();
        }
    }

    public function validation()
    {
        $zenkipay = new Zenkipay();
        if ($zenkipay->active) {
            $zenkipay->processPayment(Tools::getValue('zenkipay_trx_id'));
        } else {
            $error = $this->module->l('There was a problem with your payment.');
            $this->context->cookie->__set('zenkipay_error', $error);
            $controller = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc.php' : 'order.php';
            $redirect = $this->context->link->getPageLink($controller) . (strpos($controller, '?') !== false ? '&' : '?') . 'step=3#zenkipay_error';
            Tools::redirect($redirect);
        }
    }
}
