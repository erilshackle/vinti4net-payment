<?php

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/**
 * Classe Vinti4Net a.k.a Vinti4Pay
 *
 * PHP SDK for integrating with the Vinti4Net payment system (SISP Cabo Verde, MOP021 service).
 * This class allows creating, sending, and validating online payment transactions, including 3DS support.
 *
 * Main features:
 * - Prepare a payment (preparePayment)
 * - Generate fingerprints for security
 * - Generate auto-submitting HTML forms
 * - Process callback responses from Vinti4Net
 *
 * @package App\Services
 * @version 1.0.0
 * @license MIT
 * @link https://www.vinti4.cv/documentation.aspx?id=585
 */
class Vinti4Net
{
    const DEFAULT_BASE_URL = "https://mc.vinti4net.cv/BizMPIOnUs/CardPayment";

    const TRANSACTION_TYPE_COMPRA = '1';
    const TRANSACTION_TYPE_PAGAMENTO_SERVICO = '2';
    const TRANSACTION_TYPE_RECARGA = '3';

    const CURRENCY_CVE = '132';
    const SUCCESS_MESSAGE_TYPES = ['8', '10', 'P', 'M'];

    private string $posID;
    private string $posAutCode;
    private string $baseUrl;

    public function __construct(string $posID, string $posAutCode, ?string $endpoint = null)
    {
        $this->posID = $posID;
        $this->posAutCode = $posAutCode;
        $this->baseUrl = $endpoint ?? self::DEFAULT_BASE_URL;
    }

    // ----------------------------------------------------------
    // INTERFACE PÚBLICA
    // ----------------------------------------------------------

    /**
     * Cria formulário completo para uma COMPRA (3DS).
     * 
     * @throws \InvalidArgumentException se os dados de billing estiverem incompletos.
     */
    public function createPurchaseForm(
        float|string $amount,
        string $responseUrl,
        array $billing,
        array $extras = []
    ): string {
        $required = ['billAddrCountry', 'billAddrCity', 'billAddrLine1', 'billAddrPostCode', 'email'];
        $missing = array_diff($required, array_keys($billing));

        if (!empty($missing)) {
            throw new \InvalidArgumentException("Campos obrigatórios de billing ausentes: " . implode(', ', $missing));
        }

        // Valida e-mail
        if (!filter_var($billing['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("O campo 'email' contém um formato inválido.");
        }

        // Monta os dados finais
        $dados = array_merge($extras, [
            'billing' => $billing,
            'transactionCode' => self::TRANSACTION_TYPE_COMPRA
        ]);

        return $this->createGenericForm($amount, $responseUrl, $dados, self::TRANSACTION_TYPE_COMPRA);
    }

    /**
     * Cria formulário completo para PAGAMENTO DE SERVIÇO.
     */
    public function createServicePaymentForm(
        float|string $amount,
        string $responseUrl,
        string $entityCode,
        string $referenceNumber,
        array $extras = []
    ): string {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("O valor da transação deve ser maior que zero.");
        }

        if (!ctype_digit($entityCode) || !ctype_digit($referenceNumber)) {
            throw new \InvalidArgumentException("Os campos 'entityCode' e 'referenceNumber' devem conter apenas números.");
        }

        $dados = array_merge($extras, [
            'entityCode' => $entityCode,
            'referenceNumber' => $referenceNumber,
            'transactionCode' => self::TRANSACTION_TYPE_PAGAMENTO_SERVICO
        ]);

        return $this->createGenericForm($amount, $responseUrl, $dados, self::TRANSACTION_TYPE_PAGAMENTO_SERVICO);
    }

    /**
     * Cria formulário completo para RECARGA.
     */
    public function createRechargeForm(
        float|string $amount,
        string $responseUrl,
        string $entityCode,
        string $referenceNumber,
        array $extras = []
    ): string {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("O valor da transação deve ser maior que zero.");
        }

        if (!ctype_digit($entityCode) || !ctype_digit($referenceNumber)) {
            throw new \InvalidArgumentException("Os campos 'entityCode' e 'referenceNumber' devem conter apenas números.");
        }

        $dados = array_merge($extras, [
            'entityCode' => $entityCode,
            'referenceNumber' => $referenceNumber,
            'transactionCode' => self::TRANSACTION_TYPE_RECARGA
        ]);

        return $this->createGenericForm($amount, $responseUrl, $dados, self::TRANSACTION_TYPE_RECARGA);
    }


    /**
     * Método privado central que prepara e gera o HTML do formulário de pagamento.
     */
    private function createGenericForm(float|string $amount, string $responseUrl, array $dados, string $transactionCode): string
    {
        $dados['transactionCode'] = $transactionCode;
        $paymentData = $this->prepararPagamento($amount, $responseUrl, $dados);
        return $this->buildHtmlForm($paymentData);
    }

    // ----------------------------------------------------------
    // 🔧 Implementação dos métodos internos
    // ----------------------------------------------------------

    private function GerarFingerPrintEnvio(array $data): string
    {
        $entityCode = !empty($data['entityCode']) ? (int)$data['entityCode'] : '';
        $referenceNumber = !empty($data['referenceNumber']) ? (int)$data['referenceNumber'] : '';
        $amountInMille = (int)((float)$data['amount'] * 1000);

        $toHash = base64_encode(hash('sha512', $this->posAutCode, true)) .
            $data['timeStamp'] .
            $amountInMille .
            $data['merchantRef'] .
            $data['merchantSession'] .
            $data['posID'] .
            $data['currency'] .
            $data['transactionCode'] .
            $entityCode .
            $referenceNumber;

        return base64_encode(hash('sha512', $toHash, true));
    }

    private function buildPurchaseRequest(string $billAddrCountry, string $billAddrCity, string $billAddrLine1, string $billAddrPostCode, string $email, array $additionalData = []): string
    {
        $payload = [
            'billAddrCountry' => $billAddrCountry,
            'billAddrCity' => $billAddrCity,
            'billAddrLine1' => $billAddrLine1,
            'billAddrPostCode' => $billAddrPostCode,
            'email' => $email,
        ];
        $payload = array_merge($payload, $additionalData);

        if (($payload['addrMatch'] ?? 'N') === 'Y') {
            $payload['shipAddrCountry'] = $payload['billAddrCountry'];
            $payload['shipAddrCity'] = $payload['billAddrCity'];
            $payload['shipAddrLine1'] = $payload['billAddrLine1'];
            $payload['shipAddrPostCode'] = $payload['billAddrPostCode'];
        }

        $json = json_encode(array_filter($payload, fn($v) => !empty($v) || is_numeric($v)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) throw new \Exception("Erro ao codificar PurchaseRequest");
        return base64_encode($json);
    }

    public function prepararPagamento(float|string $amount, string $responseUrl, array $aditionals = []): array
    {
        $billingData = $aditionals['billing'] ?? [];
        $dateTime = date('Y-m-d H:i:s');

        $fields = [
            'transactionCode' => $aditionals['transactionCode'] ?? self::TRANSACTION_TYPE_COMPRA,
            'posID' => $this->posID,
            'merchantRef' => $aditionals['merchantRef'] ?? 'R' . date('YmdHis'),
            'merchantSession' => $aditionals['merchantSession'] ?? 'S' . date('YmdHis'),
            'amount' => (int)(float)$amount,
            'currency' => $aditionals['currency'] ?? self::CURRENCY_CVE,
            'is3DSec' => '1',
            'urlMerchantResponse' => $responseUrl,
            'languageMessages' => $aditionals['languageMessages'] ?? 'pt',
            'timeStamp' => $dateTime,
            'fingerprintversion' => '1',
            'entityCode' => $aditionals['entityCode'] ?? '',
            'referenceNumber' => $aditionals['referenceNumber'] ?? '',
        ];

        // Se for COMPRA, processa o purchaseRequest
        if ($fields['transactionCode'] === self::TRANSACTION_TYPE_COMPRA && !empty($billingData)) {
            $fields['purchaseRequest'] = $this->buildPurchaseRequest(
                $billingData['billAddrCountry'],
                $billingData['billAddrCity'],
                $billingData['billAddrLine1'],
                $billingData['billAddrPostCode'],
                $billingData['email'],
                array_diff_key($billingData, array_flip(['billAddrCountry', 'billAddrCity', 'billAddrLine1', 'billAddrPostCode', 'email']))
            );
        }

        $fields['fingerprint'] = $this->GerarFingerPrintEnvio($fields);
        $postUrl = $this->baseUrl .
            "?FingerPrint=" . urlencode($fields["fingerprint"]) .
            "&TimeStamp=" . urlencode($fields["timeStamp"]) .
            "&FingerPrintVersion=" . urlencode($fields["fingerprintversion"]);

        return ['postUrl' => $postUrl, '' => $fields];
    }

    private function buildHtmlForm(array $paymentData): string
    {
        $inputs = '';
        foreach ($paymentData['fields'] as $key => $value) {
            $inputs .= "<input type='hidden' name='" . htmlspecialchars($key) . "' value='" . htmlspecialchars($value) . "'>";
        }

        return "
        <html>
            <head><title>Pagamento Vinti4Net</title></head>
            <body onload='document.forms[0].submit()'>
                <h5>Processando o pagamento... Por favor, aguarde.</h5>
                <form action='{$paymentData['postUrl']}' method='post'>{$inputs}</form>
            </body>
        </html>";
    }

    // ----------------------------------------------------------
    // 🔍 Validação de resposta (mantida)
    // ----------------------------------------------------------
    private function GerarFingerPrintRespostaBemSucedida(array $data): string
    {
        $reference = !empty($data['merchantRespReferenceNumber']) ? (int)$data['merchantRespReferenceNumber'] : '';
        $entity = !empty($data['merchantRespEntityCode']) ? (int)$data['merchantRespEntityCode'] : '';
        $reloadCode = $data['merchantRespReloadCode'] ?? '';
        $additionalErrorMessage = trim($data['merchantRespAdditionalErrorMessage'] ?? '');

        $toHash = base64_encode(hash('sha512', $this->posAutCode, true)) .
            $data["messageType"] . $data["merchantRespCP"] . $data["merchantRespTid"] .
            $data["merchantRespMerchantRef"] . $data["merchantRespMerchantSession"] .
            ((int)((float)$data["merchantRespPurchaseAmount"] * 1000)) .
            $data["merchantRespMessageID"] . $data["merchantRespPan"] . $data["merchantResp"] .
            $data["merchantRespTimeStamp"] . $reference . $entity .
            $data["merchantRespClientReceipt"] . $additionalErrorMessage . $reloadCode;

        return base64_encode(hash('sha512', $toHash, true));
    }
    /**
     * Processa e valida a resposta (callback) do Vinti4Net.
     *
     * @param array $postData Dados recebidos no callback ($_POST)
     * @return array{
     *     status: string,
     *     message: string,
     *     success: bool,
     *     data: array,
     *     dcc?: array|null,
     *     debug?: array,
     *     detail?: string
     * }
     */
    public function processarResposta(array $postData): array
    {
        // Estrutura base
        $result = [
            'status' => 'ERROR',
            'message' => 'Erro desconhecido na transação.',
            'success' => false,
            'data' => $postData,
            'dcc' => null,
        ];

        // -----------------------------------------------------------
        // 1️⃣ Cancelamento pelo utilizador
        // -----------------------------------------------------------
        if (($postData["UserCancelled"] ?? '') === "true") {
            $result['status'] = 'CANCELLED';
            $result['message'] = 'Utilizador cancelou a requisição de pagamento.';
            return $result;
        }

        // -----------------------------------------------------------
        // 2️⃣ Verificar e processar o DCC (Dynamic Currency Conversion)
        // -----------------------------------------------------------
        if (!empty($postData['merchantRespDCCData'])) {
            $decoded = json_decode($postData['merchantRespDCCData'], true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $result['dcc'] = $decoded;
            } else {
                error_log("DCC Data inválido recebido: " . $postData['merchantRespDCCData']);
                $result['dcc'] = null;
            }
        }

        // -----------------------------------------------------------
        // 3️⃣ Verificar sucesso da transação via messageType
        // -----------------------------------------------------------
        if (isset($postData["messageType"]) && in_array($postData["messageType"], self::SUCCESS_MESSAGE_TYPES)) {

            $calcFingerprint = $this->GerarFingerPrintRespostaBemSucedida($postData);
            $receivedFingerprint = $postData["resultFingerPrint"] ?? '';

            if ($receivedFingerprint === $calcFingerprint) {
                $result['status'] = 'SUCCESS';
                $result['message'] = 'Transação válida e fingerprint verificado.';
                $result['success'] = true;
            } else {
                $result['status'] = 'INVALID_FINGERPRINT';
                $result['message'] = 'Transação processada, mas fingerprint inválido.';
                $result['success'] = true; // Transação pode ter sido aprovada, mas requer atenção
                $result['debug'] = [
                    'recebido' => $receivedFingerprint,
                    'calculado' => $calcFingerprint
                ];
            }

            return $result;
        }

        // -----------------------------------------------------------
        // 4️⃣ Caso de erro geral
        // -----------------------------------------------------------
        if (!empty($postData["merchantRespErrorDescription"])) {
            $result['message'] = $postData["merchantRespErrorDescription"];
        }

        if (!empty($postData["merchantRespErrorDetail"])) {
            $result['detail'] = $postData["merchantRespErrorDetail"];
        }

        // Retorno final unificado
        return $result;
    }
}
