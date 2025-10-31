<?php

use PHPUnit\Framework\TestCase;
use Erilshk\Vinti4Pay\Vinti4Pay;
use Erilshk\Vinti4Pay\Vinti4Refund;
use Erilshk\Vinti4Pay\Vinti4Result;

class Vinti4PayTest extends TestCase
{
    private Vinti4Pay $vinti4;
    private Vinti4Refund $refund;

    protected function setUp(): void
    {
        $posID = '90000443';
        $posAuthCode = 'SEGREDO123';

        $this->vinti4 = new Vinti4Pay($posID, $posAuthCode, 'https://sandbox.vinti4net.cv/test');
        $this->refund = new Vinti4Refund($posID, $posAuthCode, 'https://sandbox.vinti4net.cv/test');
    }

    public function testPreparePaymentReturnsArray(): void
    {
        $billing = [
            'billAddrCountry' => 'CV',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua da Paz, 123',
            'billAddrPostCode' => '7600',
            'email' => 'cliente@example.com'
        ];

        $result = $this->vinti4->preparePayment(1000, 'https://meusite.com/callback.php', [
            'billing' => $billing
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('postUrl', $result);
        $this->assertArrayHasKey('fields', $result);
        $this->assertArrayHasKey('fingerprint', $result['fields']);
    }

    public function testGenerateHtmlFormContainsInputFields(): void
    {
        $billing = [
            'billAddrCountry' => 'CV',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua da Paz, 123',
            'billAddrPostCode' => '7600',
            'email' => 'cliente@example.com'
        ];

        $paymentData = $this->vinti4->preparePayment(1000, 'https://meusite.com/callback.php', [
            'billing' => $billing
        ]);

        $htmlForm = $this->vinti4->generateHtmlForm($paymentData);

        $this->assertStringContainsString('<form', $htmlForm);
        $this->assertStringContainsString('input type=\'hidden\'', $htmlForm);
        $this->assertStringContainsString(htmlspecialchars($paymentData['fields']['fingerprint']), $htmlForm);
    }

    public function testProcessResponseSuccess(): void
    {
        $postData = [
            'messageType' => '8',
            'resultFingerPrint' => '',
            'merchantRespMerchantRef' => 'R123',
            'merchantRespMerchantSession' => 'S123',
            'merchantRespPurchaseAmount' => '1000',
            'merchantRespPurchaseCurrency' => '132',
            'merchantRespTimeStamp' => date('Y-m-d H:i:s'),
        ];

        // Calcula fingerprint correta
        $postData['resultFingerPrint'] = $this->invokePrivateMethod($this->vinti4, 'generateResponseFingerprint', [$postData]);

        $resultArray = $this->vinti4->processResponse($postData);
        $result = new Vinti4Result($resultArray);

        $this->assertTrue($result->isSuccessful);
        $this->assertEquals('SUCCESS', $result->status);
    }

    public function testPrepareRefundReturnsArray(): void
    {
        $refundData = $this->refund->prepareRefund(
            'R123', 'S123', 1000, '1', 'T123', 'https://meusite.com/refund-callback.php'
        );

        $this->assertIsArray($refundData);
        $this->assertArrayHasKey('postUrl', $refundData);
        $this->assertArrayHasKey('fields', $refundData);
        $this->assertArrayHasKey('fingerPrint6', $refundData['fields']);
    }

    /**
     * Helper para invocar métodos privados
     */
    private function invokePrivateMethod(object $object, string $methodName, array $args = [])
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}
