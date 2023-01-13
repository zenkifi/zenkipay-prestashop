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
use Svix\Webhook;

require_once dirname(__FILE__) . '/../../lib/svix/init.php';
require_once dirname(__FILE__) . '/../../zenkipay.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class ZenkipayNotificationModuleFrontController extends ModuleFrontController
{
    private $zenkipay;

    public function __construct()
    {
        $this->auth = false;
        parent::__construct();
        $this->context = Context::getContext();
        $this->zenkipay = new Zenkipay();
    }

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        $payload = Tools::file_get_contents('php://input');
        $headers = apache_request_headers();
        $svix_headers = [];
        foreach ($headers as $key => $value) {
            $header = strtolower($key);
            $svix_headers[$header] = $value;
        }

        Logger::addLog('Webhook payload: ' . $payload, 1, null, null, null, true);

        try {
            $secret = $this->zenkipay->getWebhookSigningSecret();
            $wh = new Webhook($secret);
            $json = $wh->verify($payload, $svix_headers);
            $payment = json_decode($json->flatData);

            if ($payment->paymentInfo->cryptoPayment->transactionStatus != 'COMPLETED' || !$payment->cartId) {
                header('HTTP/1.1 400 Bad Request');
                header('Content-type: application/json');
                echo json_encode(['error' => true, 'message' => 'Transaction status is no tcompleted or cartId is empty.']);
                exit;
            }

            $order_id = Order::getOrderByCartId((int) $payment->cartId);

            if (empty($order_id)) {
                $msg = 'OrderID not found using Order::getOrderByCartId(' . $payment->cartId . ')';
                header('HTTP/1.1 404 Not Found');
                header('Content-type: application/json');
                echo json_encode(['error' => true, 'message' => $msg]);
                exit;
            }

            $order = new Order((int) $order_id);
            $order_state = $order->getCurrentOrderState();

            if ($order_state && !$order_state->paid) {
                $order->total_paid = $payment->breakdown->grandTotalAmount;
                $order->update();

                $order_history = new OrderHistory();
                $order_history->id_order = (int) $order_id;
                $order_history->changeIdOrderState(Configuration::get('PS_OS_PAYMENT'), (int) $order_id);
                $order_history->addWithemail();

                $payments = $order->getOrderPaymentCollection();
                if (isset($payments[0])) {
                    $payments[0]->transaction_id = pSQL($payment->zenkiOrderId);
                    $payments[0]->save();
                }

                $this->zenkipay->updateZenkiOrder($payment->zenkiOrderId, ['orderId' => $order_id]);
            }
        } catch (Exception $e) {
            Logger::addLog('Webhook Notification - BAD REQUEST 400: ' . $e->getMessage(), 3, null, null, null, true);
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-type: application/json');
            echo json_encode(['error' => true, 'message' => 'An unexpected error has occurred']);
            exit;
        }

        header('HTTP/1.1 200 OK');
        header('Content-type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    protected function createCartRule($cart, $discount)
    {
        $cart_rule = new CartRule();
        $language_ids = LanguageCore::getIDs(false);

        foreach ($language_ids as $language_id) {
            $cart_rule->name[$language_id] = $this->l('Zenkipay rule');
            $cart_rule->description = $this->l('Rule created automatically by Zenkipay');
        }

        $now = time();
        $cart_rule->code = 'ZENKI' . $now;
        $cart_rule->date_from = date('Y-m-d H:i:s', $now);
        $cart_rule->date_to = date('Y-m-d H:i:s', strtotime('+1 minute'));
        $cart_rule->reduction_amount = $discount;
        $cart_rule->reduction_tax = true;
        $cart_rule->reduction_currency = $cart->id_currency;
        $cart_rule->add();

        return $cart_rule;
    }

    protected function addDiscount($order, $cart, $cart_rule)
    {
        $cart->addCartRule($cart_rule->id);

        $total_discounts = $cart_rule->reduction_amount;
        $total_discounts_tax_incl = $cart_rule->reduction_amount;
        $total_discounts_tax_excl = $cart_rule->reduction_amount;
        $total_paid = $order->total_paid - $total_discounts;
        $total_paid_tax_excl = $order->total_paid_tax_excl - $total_discounts_tax_excl;

        Db::getInstance()->Execute(
            'UPDATE ' .
                _DB_PREFIX_ .
                'orders  SET total_discounts = ' .
                (float) $total_discounts .
                ', total_discounts_tax_incl = ' .
                (float) $total_discounts_tax_incl .
                ', total_discounts_tax_excl = ' .
                (float) $total_discounts_tax_excl .
                ', total_paid = ' .
                (float) $total_paid .
                ', total_paid_tax_incl = ' .
                (float) $total_paid .
                ', total_paid_tax_excl = ' .
                (float) $total_paid_tax_excl .
                ', total_paid_real = ' .
                (float) $total_paid .
                ' WHERE id_order = ' .
                (int) $order->id
        );

        Db::getInstance()->Execute(
            'INSERT INTO ' .
                _DB_PREFIX_ .
                'order_cart_rule (`id_order`, `id_cart_rule`, `id_order_invoice`, `name`, `value`, `value_tax_excl`, `free_shipping`, `deleted`) VALUES (' .
                (int) $order->id .
                ', ' .
                (int) $cart_rule->id .
                ', "0", "Crypto Love", ' .
                (float) $total_discounts .
                ', ' .
                (float) $total_discounts_tax_excl .
                ', 0, 0)'
        );
    }
}
