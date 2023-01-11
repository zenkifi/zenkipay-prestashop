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

namespace Svix;

final class WebhookTest extends \PHPUnit\Framework\TestCase
{
    private const TOLERANCE = 5 * 60;

    public function testValidSignatureIsValidAndReturnsJson()
    {
        $testPayload = new TestPayload(time());

        $wh = new \Svix\Webhook($testPayload->secret);
        $json = $wh->verify($testPayload->payload, $testPayload->header);

        $this->assertEquals($json['test'], 2432232315, 'did not return expected json');
    }

    public function testValidBrandlessSignatureIsValidAndReturnsJson()
    {
        $testPayload = new TestPayload(time());
        $unbrandedHeaders = [
            'webhook-id' => $testPayload->header['svix-id'],
            'webhook-signature' => $testPayload->header['svix-signature'],
            'webhook-timestamp' => $testPayload->header['svix-timestamp'],
        ];
        $testPayload->header = $unbrandedHeaders;

        $wh = new \Svix\Webhook($testPayload->secret);
        $json = $wh->verify($testPayload->payload, $testPayload->header);

        $this->assertEquals($json['test'], 2432232315, 'did not return expected json');
    }

    public function testInvalidSignatureThrowsException()
    {
        $this->expectException(\Svix\Exception\WebhookVerificationException::class);
        $this->expectExceptionMessage('No matching signature found');

        $testPayload = new TestPayload(time());
        $testPayload->header['svix-signature'] = 'v1,dawfeoifkpqwoekfpqoekf';

        $wh = new \Svix\Webhook($testPayload->secret);
        $wh->verify($testPayload->payload, $testPayload->header);
    }

    public function testMissingIdThrowsException()
    {
        $this->expectException(\Svix\Exception\WebhookVerificationException::class);
        $this->expectExceptionMessage('Missing required headers');

        $testPayload = new TestPayload(time());
        unset($testPayload->header['svix-id']);

        $wh = new \Svix\Webhook($testPayload->secret);
        $wh->verify($testPayload->payload, $testPayload->header);
    }

    public function testMissingTimestampThrowsException()
    {
        $this->expectException(\Svix\Exception\WebhookVerificationException::class);
        $this->expectExceptionMessage('Missing required headers');

        $testPayload = new TestPayload(time());
        unset($testPayload->header['svix-timestamp']);

        $wh = new \Svix\Webhook($testPayload->secret);
        $wh->verify($testPayload->payload, $testPayload->header);
    }

    public function testMissingSignatureThrowsException()
    {
        $this->expectException(\Svix\Exception\WebhookVerificationException::class);
        $this->expectExceptionMessage('Missing required headers');

        $testPayload = new TestPayload(time());
        unset($testPayload->header['svix-signature']);

        $wh = new \Svix\Webhook($testPayload->secret);
        $wh->verify($testPayload->payload, $testPayload->header);
    }

    public function testOldTimestampThrowsException()
    {
        $this->expectException(\Svix\Exception\WebhookVerificationException::class);
        $this->expectExceptionMessage('Message timestamp too old');

        $testPayload = new TestPayload(time() - self::TOLERANCE - 1);

        $wh = new \Svix\Webhook($testPayload->secret);
        $wh->verify($testPayload->payload, $testPayload->header);
    }

    public function testNewTimestampThrowsException()
    {
        $this->expectException(\Svix\Exception\WebhookVerificationException::class);
        $this->expectExceptionMessage('Message timestamp too new');

        $testPayload = new TestPayload(time() + self::TOLERANCE + 1);

        $wh = new \Svix\Webhook($testPayload->secret);
        $wh->verify($testPayload->payload, $testPayload->header);
    }

    public function testMultiSigPayloadIsValid()
    {
        $testPayload = new TestPayload(time());
        $sigs = [
            'v1,Ceo5qEr07ixe2NLpvHk3FH9bwy/WavXrAFQ/9tdO6mc=',
            'v2,Ceo5qEr07ixe2NLpvHk3FH9bwy/WavXrAFQ/9tdO6mc=',
            $testPayload->header['svix-signature'], // valid signature
            'v1,Ceo5qEr07ixe2NLpvHk3FH9bwy/WavXrAFQ/9tdO6mc=',
        ];
        $testPayload->header['svix-signature'] = implode(' ', $sigs);

        $wh = new \Svix\Webhook($testPayload->secret);
        $wh->verify($testPayload->payload, $testPayload->header);
    }

    public function testSignatureVerificationWithAndWithoutPrefix()
    {
        $testPayload = new TestPayload(time());

        $wh = new \Svix\Webhook($testPayload->secret);
        $wh->verify($testPayload->payload, $testPayload->header);

        $wh = new \Svix\Webhook('whsec_' . $testPayload->secret);
        $wh->verify($testPayload->payload, $testPayload->header);
    }

    public function testSignFunctionWorks()
    {
        $key = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';
        $msgId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
        $timestamp = 1614265330;
        $payload = '{"test": 2432232314}';
        $expected = 'v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=';

        $wh = new \Svix\Webhook($key);

        $signature = $wh->sign($msgId, $timestamp, $payload);
        $this->assertEquals($signature, $expected, 'did not return expected signature');
    }

    public function testInvalidFloatTimestamp()
    {
        $this->expectException(\Svix\Exception\WebhookSigningException::class);
        $key = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';
        $msgId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
        $timestamp = '161426533.0';
        $payload = '{"test": 2432232314}';
        $expected = 'v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=';

        $wh = new \Svix\Webhook($key);

        $signature = $wh->sign($msgId, $timestamp, $payload);
    }

    public function testInvalidStringTimestamp()
    {
        $this->expectException(\Svix\Exception\WebhookSigningException::class);
        $key = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';
        $msgId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
        $timestamp = 'invalid timestamp';
        $payload = '{"test": 2432232314}';
        $expected = 'v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=';

        $wh = new \Svix\Webhook($key);

        $signature = $wh->sign($msgId, $timestamp, $payload);
    }

    public function testInvalidNegativeTimestamp()
    {
        $this->expectException(\Svix\Exception\WebhookSigningException::class);
        $key = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';
        $msgId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
        $timestamp = '-161426533';
        $payload = '{"test": 2432232314}';
        $expected = 'v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=';

        $wh = new \Svix\Webhook($key);

        $signature = $wh->sign($msgId, $timestamp, $payload);
    }
}
