<?php

use PHPUnit\Framework\TestCase;
use Erilshk\Vinti4Net\PaymentClient;
use Erilshk\Vinti4Net\PaymentRequest;

class PaymentRequestTest extends TestCase
{
    protected PaymentClient $client;

    protected function setUp(): void
    {
        // Instanciar cliente com credenciais fictícias
        $this->client = new PaymentClient('90000443', 'SECRET123', 'https://sandbox.vinti4net.cv/payment');
    }

    public function testPaymentRequestCanBeCreated(): void
    {
        $payment = new PaymentRequest(1000.50, 'https://meusite.com/callback', 1);

        $this->assertSame('1000.5', $payment->amount);
        $this->assertSame('1', $payment->transactionCode);
        $this->assertSame('https://meusite.com/callback', $payment->responseUrl);
        $this->assertIsArray($payment->billingData);
        $this->assertEmpty($payment->billingData);
    }

    public function testSetBillingPopulatesBillingData(): void
    {
        $payment = new PaymentRequest(500, 'https://callback.test', 1);

        $payment->setBilling(
            'user@exemplo.com',
            '132',
            'Cidade',
            'Rua Teste 123',
            '7600',
            ['chAccAgeInd' => '01'],
            ['addrMatch' => 'Y']
        );

        $this->assertArrayHasKey('email', $payment->billingData);
        $this->assertSame('user@exemplo.com', $payment->billingData['email']);
        $this->assertSame('Y', $payment->billingData['addrMatch']);
        $this->assertArrayHasKey('acctInfo', $payment->billingData);
        $this->assertSame(['chAccAgeInd' => '01'], $payment->billingData['acctInfo']);
    }

    public function testRenderPaymentFormGeneratesFingerprintAndHtml(): void
    {
        $payment = $this->client->createPurchasePayment(1200, 'https://callback.test');

        $payment->setBilling(
            'cliente@exemplo.com',
            '132',
            'Praia',
            'Av. Marginal 123',
            '7600'
        );

        $html = $this->client->renderPaymentForm($payment);

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('input type=\'hidden\'', $html);
        $this->assertStringContainsString('fingerprint', $html);
        $this->assertStringContainsString('purchaseRequest', $html);

        // Validar que a URL de POST contém o fingerprint
        preg_match('/action=[\'"]([^\'"]+)[\'"]/', $html, $matches);
        $this->assertNotEmpty($matches);
        $postUrl = $matches[1];
        $this->assertStringContainsString('FingerPrint=', $postUrl);
        $this->assertStringContainsString('TimeStamp=', $postUrl);
        $this->assertStringContainsString('FingerPrintVersion=', $postUrl);
    }

    public function testCreateServicePaymentGeneratesCorrectFields(): void
    {
        $payment = $this->client->createServicePayment(800, 'https://callback.test', '10', '5000');

        $this->assertSame('2', $payment->transactionCode);
        $this->assertSame('10', $payment->entityCode);
        $this->assertSame('5000', $payment->referenceNumber);

        // Processar para gerar fields + fingerprint
        $method = new ReflectionMethod(PaymentClient::class, 'createPayment');
        $method->setAccessible(true);
        $result = $method->invoke($this->client, $payment);

        $this->assertArrayHasKey('fields', $result);
        $this->assertArrayHasKey('fingerprint', $result['fields']);
        $this->assertNotEmpty($result['fields']['fingerprint']);
    }
}
