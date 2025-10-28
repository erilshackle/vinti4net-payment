<?php

use PHPUnit\Framework\TestCase;
use Erilshk\Vinti4Net\PaymentClient;
use Erilshk\Vinti4Net\PaymentRequest;

class PaymentClientTest extends TestCase
{
    protected PaymentClient $client;

    protected function setUp(): void
    {
        $this->client = new PaymentClient('90000443', 'SECRET123');
    }

    public function testCanInstantiatePaymentClient(): void
    {
        $this->assertInstanceOf(PaymentClient::class, $this->client);
    }

    public function testCreateServicePaymentReturnsPaymentRequest(): void
    {
        $req = $this->client->createServicePayment(
            1000,
            'https://meusite.com/callback',
            '12345',
            '67890'
        );

        $this->assertInstanceOf(PaymentRequest::class, $req);
        $this->assertSame('2', $req->transactionCode);
        $this->assertSame('12345', $req->entityCode);
        $this->assertSame('67890', $req->referenceNumber);
        $this->assertSame('1000', $req->amount);
    }


    public function testGeneratePurchaseRequestThrowsExceptionForMissingFields(): void
    {
        $this->expectException(\Erilshk\Vinti4Net\Exception\ValidationException::class);

        $method = new ReflectionMethod(PaymentClient::class, 'generatePurchaseRequest');
        $method->setAccessible(true);
        $method->invoke($this->client, ['email' => 'teste@exemplo.com']); // faltando campos
    }


    // ============================================================
    // TESTE: FINGERPRINT DE ENVIO (REQUEST)
    // ============================================================
    public function testGenerateRequestFingerPrintProducesExpectedBase64(): void
    {
        // usa Reflection para acessar o método protegido
        $method = new ReflectionMethod(PaymentClient::class, 'generateRequestFingerPrint');
        $method->setAccessible(true);

        $data = [
            'timeStamp'        => '2025-10-28 12:00:00',
            'amount'           => 100.00, // CVE
            'merchantRef'      => 'R12345',
            'merchantSession'  => 'S67890',
            'posID'            => '90000443',
            'currency'         => '132',
            'transactionCode'  => '2',
            'entityCode'       => '123',
            'referenceNumber'  => '456',
        ];

        // executa o método
        $fingerprint = $method->invoke($this->client, $data);

        // deve retornar uma string base64 válida
        $this->assertNotEmpty($fingerprint);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $fingerprint);

        // verifica consistência (mesmos dados → mesmo hash)
        $fingerprint2 = $method->invoke($this->client, $data);
        $this->assertSame($fingerprint, $fingerprint2);

        // altera algo para garantir que muda
        $data['amount'] = 101.00;
        $fingerprint3 = $method->invoke($this->client, $data);
        $this->assertNotSame($fingerprint, $fingerprint3);
    }

    // ============================================================
    // TESTE: FINGERPRINT DE RESPOSTA (RESPONSE)
    // ============================================================
    public function testGenerateSuccessfulResponseFingerPrintProducesExpectedBase64(): void
    {
        $method = new ReflectionMethod(PaymentClient::class, 'generateSuccessfulResponseFingerPrint');
        $method->setAccessible(true);

        $data = [
            'messageType'                    => '8',
            'merchantRespCP'                 => '90000443',
            'merchantRespTid'                => 'TST123',
            'merchantRespMerchantRef'        => 'R12345',
            'merchantRespMerchantSession'    => 'S67890',
            'merchantRespPurchaseAmount'     => '100.00',
            'merchantRespMessageID'          => 'MSG01',
            'merchantRespPan'                => '411111******1111',
            'merchantResp'                   => 'C',
            'merchantRespTimeStamp'          => '2025-10-28 12:05:00',
            'merchantRespReferenceNumber'    => '456',
            'merchantRespEntityCode'         => '123',
            'merchantRespClientReceipt'      => 'OK',
            'merchantRespAdditionalErrorMessage' => '',
            'merchantRespReloadCode'         => '',
        ];

        $fingerprint = $method->invoke($this->client, $data);

        $this->assertNotEmpty($fingerprint);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $fingerprint);

        // deve ser determinístico
        $fingerprint2 = $method->invoke($this->client, $data);
        $this->assertSame($fingerprint, $fingerprint2);

        // se mudar algo, o hash deve mudar
        $data['merchantRespPan'] = '400000******0000';
        $fingerprint3 = $method->invoke($this->client, $data);
        $this->assertNotSame($fingerprint, $fingerprint3);
    }

    // ============================================================
    // TESTE: DIFERENTES CHAVES SECRETAS GERAM FINGERPRINT DIFERENTE
    // ============================================================
    public function testDifferentPosAutCodeProducesDifferentFingerPrint(): void
    {
        $method = new ReflectionMethod(PaymentClient::class, 'generateRequestFingerPrint');
        $method->setAccessible(true);

        $data = [
            'timeStamp'        => '2025-10-28 12:00:00',
            'amount'           => 50.00,
            'merchantRef'      => 'R111',
            'merchantSession'  => 'S222',
            'posID'            => '90000443',
            'currency'         => '132',
            'transactionCode'  => '2',
            'entityCode'       => '111',
            'referenceNumber'  => '222',
        ];

        $client1 = new PaymentClient('90000443', 'SECRET123');
        $client2 = new PaymentClient('90000443', 'OTHERSECRET');

        $f1 = $method->invoke($client1, $data);
        $f2 = $method->invoke($client2, $data);

        $this->assertNotSame($f1, $f2);
    }
}
