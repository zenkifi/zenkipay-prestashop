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
class ZenkipayOrderModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        $this->ajax = true;
    }

    public function displayAjax()
    {
        $cart_id = Tools::getValue('cart_id');
        Logger::addLog('#cart_id => ' . $cart_id, 1, null, 'Cart', (int) $this->context->cart->id, true);

        try {
            $zenkipay = new Zenkipay();
            $response = $zenkipay->createOrder();
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - createOrder getMessage ' . $e->getMessage(), 3, $e->getCode(), null, null, true);
            Logger::addLog('Zenkipay - createOrder getLine ' . $e->getLine(), 3, $e->getCode(), null, null, true);

            $response = [
                'error' => true,
                'message' => 'An unexpected error has occurred',
            ];
        }

        echo json_encode($response);
        exit;
    }
}
