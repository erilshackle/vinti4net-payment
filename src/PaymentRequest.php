<?php

namespace Erilshk\Vinti4Net;

/**
 * Classe DTO (Data Transfer Object) para encapsular os dados de pagamento.
 * Responsável por transportar informações entre a criação da requisição e o processamento do pagamento.
 *
 * @package Erilshk\Vinti4Net
 */
class PaymentRequest
{
    /** @var string Valor da transação em formato string (ex: "1000.50"). */
    public readonly string $amount;

    /** @var string Código do tipo de transação ('1'=Compra, '2'=Pagamento de Serviço, '3'=Recarga). */
    public readonly string $transactionCode;

    /** @var string URL de callback para notificação do resultado da transação. */
    public readonly string $responseUrl;

    /** @var string Referência única do comerciante (gerada ou fornecida). */
    public string $merchantRef;

    /** @var string Sessão única do comerciante (gerada ou fornecida). */
    public string $merchantSession;

    /** @var string|null Código da Entidade (para pagamentos de serviço ou recargas). */
    public ?string $entityCode = null;

    /** @var string|null Número de referência da transação (para pagamentos de serviço ou recargas). */
    public ?string $referenceNumber = null;

    /** @var string Código de idioma da interface/ mensagens (default: 'pt'). */
    public string $languageMessages = 'pt';

    /** @var string Código da moeda (ex: 132). */
    public string $currency;

    /** @var array<string, mixed> Dados de billing para 3DS e informações adicionais do cliente. */
    public array $billingData = [];

    /**
     * Construtor do DTO.
     *
     * @param float|int|string $amount Valor da transação.
     * @param string $responseUrl URL de retorno/callback.
     * @param int|string $transactionCode Código da transação (1, 2 ou 3).
     */
    public function __construct(int|float|string $amount, string $responseUrl, int|string $transactionCode)
    {
        $this->amount = (string) $amount;
        $this->responseUrl = $responseUrl;
        $this->transactionCode = (string) $transactionCode;

        // Valores default; podem ser sobrescritos pelo PaymentClient
        $this->merchantRef = '';
        $this->merchantSession = '';
        $this->currency = '132'; // Default CVE
    }

    /**
     * Define os dados de billing do cliente (necessário para transações 3DS).
     *
     * @param string $email Endereço de email do titular do cartão.
     * @param string $country Código do país (ex: '132' para CV).
     * @param string $city Cidade.
     * @param string $address Linha de endereço.
     * @param string $postalCode Código postal.
     * @param array<string, mixed> $acctInfo Campos opcionais para informações de conta (ex: chAccAgeInd, chAccChange).
     * @param array<string, mixed> $aditionals Campos adicionais opcionais (ex: addrMatch, shipAddrLine2, shipAddrLine3).
     * @return $this
     */
    public function setBilling(
        string $email,
        string $country,
        string $city,
        string $address,
        string $postalCode,
        array $acctInfo = [],
        array $aditionals = []
    ): self {
        // Campos obrigatórios do billing
        $billing = [
            'email' => $email,
            'billAddrCountry' => $country,
            'billAddrCity' => $city,
            'billAddrLine1' => $address,
            'billAddrPostCode' => $postalCode,
        ];

        // Adiciona campos opcionais de conta (3DS)
        if (!empty($acctInfo)) {
            $billing['acctInfo'] = $acctInfo;
        }

        // Merge com campos adicionais, sobrescrevendo defaults se necessário
        $this->billingData = array_merge($billing, $aditionals);

        return $this;
    }

    /**
     * Adiciona dados extras opcionais de billing sem sobrescrever os obrigatórios.
     *
     * @param array<string, mixed> $extra Dados adicionais.
     * @return $this
     */
    public function addBillingExtra(array $extra): self
    {
        $this->billingData = array_merge($this->billingData, $extra);
        return $this;
    }
}
