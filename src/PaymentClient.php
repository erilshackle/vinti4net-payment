<?php

namespace Erilshk\Vinti4Net;

use Erilshk\Vinti4Net\Exception\PaymentException;
use Erilshk\Vinti4Net\Exception\ValidationException;

/**
 * Serviço de integração com a plataforma de pagamento Vinti4Net.
 *
 * Esta classe encapsula toda a lógica de:
 *  - criação de requisições de pagamento (Purchase, Service, Recharge)
 *  - geração de FingerPrints
 *  - geração de PurchaseRequest para 3DS
 *  - renderização de formulários HTML para submissão
 *  - validação e processamento de respostas/callbacks
 * 
 * @author Eril TS
 * @license MIT
 * @see https://github.com/erilshk/vinti4net-payment
 * @package Erilshk\Vinti4Net
 */
class PaymentClient
{
    // ----------------------------------------------------------------------
    // CONSTANTES DE CONFIGURAÇÃO
    // ----------------------------------------------------------------------
    public const DEFAULT_BASE_URL = "https://mc.vinti4net.cv/BizMPIOnUs/CardPayment";

    public const TRANSACTION_TYPE_PURCHASE = '1';
    public const TRANSACTION_TYPE_SERVICE_PAYMENT = '2';
    public const TRANSACTION_TYPE_RECHARGE = '3';

    public const CURRENCY_CVE = '132'; // Escudo Caboverdiano
    public const SUCCESS_MESSAGE_TYPES = ['8', '10', 'P', 'M'];

    // ----------------------------------------------------------------------
    // CREDENCIAIS E ENDPOINT
    // ----------------------------------------------------------------------
    private string $posID;
    private string $posAutCode;
    private string $endpoint;

    /**
     ** Construtor do Vinti4Payment Client.
     *
     * @param string $posID Código do Ponto de Venda
     * @param string $posAutCode Código secreto de autorização
     * @param string|null $endpoint URL do gateway. Se null, usa o padrão
     */
    public function __construct(string $posID, string $posAutCode, ?string $endpoint = null)
    {
        $this->posID = $posID;
        $this->posAutCode = $posAutCode;
        $this->endpoint = $endpoint ?? self::DEFAULT_BASE_URL;
    }

    // ----------------------------------------------------------------------
    // PRIVATE: Funções de Geração de Fingerprint
    // ----------------------------------------------------------------------

    /**
     ** Gera o FingerPrint para o envio da transação (Request).
     * @param array $data O array de dados de envio da transação.
     * @return string O FingerPrint em Base64.
     */
    protected function generateRequestFingerPrint(array $data): string
    {
        // REMOVER POSSIVEIS ZEROS À ESQUERDA
        $entityCode = !empty($data['entityCode']) ? (int)$data['entityCode'] : '';
        $referenceNumber = !empty($data['referenceNumber']) ? (int)$data['referenceNumber'] : '';

        // CONFORME O ORIGINAL: amount é float, multiplicado por 1000 e castado para int (milésimos)
        // O campo 'amount' no $data já está em 'milésimos' se usar o createPayment (que é o que acontece),
        // mas o código original usava o valor original, então mantemos a lógica de multiplicação aqui para segurança.
        // Contudo, ao usar o método 'createPayment', o campo $fields['amount'] já está em 'milésimos'.
        // O código original tinha: $amountInMille = (int)((float)$data['amount'] * 1000);
        // Vamos usar o valor já em $data['amount'] (que é integer/string de integer) para evitar erro de arredondamento.
        # $amountInMille = (int) $data['amount'];
        $amountInMille = (int)((float)$data['amount'] * 1000);

        // CONCATENAR OS DADOS PARA O HASH FINAL
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

    /**
     ** Gera o FingerPrint para a resposta bem-sucedida (Callback/Response).
     * @param array $data Os dados de resposta (postData).
     * @return string O FingerPrint em Base64.
     */
    protected function generateSuccessfulResponseFingerPrint(array $data): string
    {
        $reference = !empty($data['merchantRespReferenceNumber']) ? (int)$data['merchantRespReferenceNumber'] : '';
        $entity = !empty($data['merchantRespEntityCode']) ? (int)$data['merchantRespEntityCode'] : '';
        $reloadCode = $data['merchantRespReloadCode'] ?? '';
        $additionalErrorMessage = trim($data['merchantRespAdditionalErrorMessage'] ?? '');

        $toHash = base64_encode(hash('sha512', $this->posAutCode, true)) .
            ($data["messageType"] ?? '') .
            ($data["merchantRespCP"] ?? '') .
            ($data["merchantRespTid"] ?? '') .
            ($data["merchantRespMerchantRef"] ?? '') .
            ($data["merchantRespMerchantSession"] ?? '') .
            ((int)((float)($data["merchantRespPurchaseAmount"] ?? 0) * 1000)) .
            ($data["merchantRespMessageID"] ?? '') .
            ($data["merchantRespPan"] ?? '') .
            ($data["merchantResp"] ?? '') .
            ($data["merchantRespTimeStamp"] ?? '') .
            $reference .
            $entity .
            ($data["merchantRespClientReceipt"] ?? '') .
            $additionalErrorMessage .
            $reloadCode;

        return base64_encode(hash('sha512', $toHash, true));
    }

    /**
     ** Implementação de generatePurchaseRequest (para validação 3DS)
     * Gera o PurchaseRequest para 3DS (compra online).
     *
     * @param array $billingData
     * @return string
     * @throws ValidationException
     * @throws PaymentException
     */
    protected function generatePurchaseRequest(array $billingData): string
    {
        // Lógica de validação e criação do Base64 do PurchaseRequest.
        $requiredKeys = ['billAddrCountry', 'billAddrCity', 'billAddrLine1', 'billAddrPostCode', 'email'];

        $missingKeys = array_diff_key(array_flip($requiredKeys), $billingData);
        if (!empty($missingKeys)) {
            throw new ValidationException("Dados de Cobrança incompletos para o PurchaseRequest. Campos faltando: " . implode(', ', array_keys($missingKeys)));
        }

        $requiredData = array_intersect_key($billingData, array_flip($requiredKeys));
        $emptyFields = array_filter($requiredData, fn($value) => empty($value));
        if (!empty($emptyFields)) {
            throw new ValidationException("Um ou mais campos de cobrança obrigatórios (PurchaseRequest) não podem ser vazios: " . implode(', ', array_keys($emptyFields)));
        }

        $payload = array_merge($requiredData, array_diff_key($billingData, array_flip($requiredKeys)));
        $addrMatch = $payload['addrMatch'] ?? 'N';

        if ($addrMatch === 'Y') {
            $payload['shipAddrCountry'] = $payload['billAddrCountry'];
            $payload['shipAddrCity'] = $payload['billAddrCity'];
            $payload['shipAddrLine1'] = $payload['billAddrLine1'];
            foreach (['billAddrLine2', 'billAddrLine3', 'billAddrPostCode', 'billAddrState'] as $key) {
                if (isset($payload[$key])) {
                    $payload[str_replace('bill', 'ship', $key)] = $payload[$key];
                }
            }
        }

        $cleanPayload = array_filter($payload, fn($value) => !empty($value) || is_numeric($value));
        $json = json_encode($cleanPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new PaymentException("Erro ao codificar os dados do PurchaseRequest para JSON.");
        }

        return base64_encode($json);
    }

    /**
     ** Gera o array de campos para submissão POST, calculando o FingerPrint, PurchaseRequest e a URL final.
     * Este é um método utilitário interno, chamado apenas por renderPaymentForm().
     *
     * @param PaymentRequest $prequest O objeto DTO contendo todos os dados.
     * @return array{postUrl:string, fields:array} Contém a URL e os campos para o POST.
     * @throws \InvalidArgumentException Se os campos obrigatórios do billing estiverem ausentes.
     */
    protected function processPaymentRequest(PaymentRequest $prequest): array
    {
        $dateTime = date('Y-m-d H:i:s');
        $amountInMille =  (int) (float) $prequest->amount;

        $fields = [
            'transactionCode' => $prequest->transactionCode,
            'posID' => $this->posID,
            'merchantRef' => $prequest->merchantRef,
            'merchantSession' => $prequest->merchantSession,
            'amount' => $amountInMille,
            'currency' => $prequest->currency ?? self::CURRENCY_CVE,
            'is3DSec' => '1',
            'urlMerchantResponse' => $prequest->responseUrl,
            'languageMessages' => $prequest->languageMessages,
            'timeStamp' => $dateTime,
            'fingerprintversion' => '1',
            'entityCode' => $prequest->entityCode ?? '',
            'referenceNumber' => $prequest->referenceNumber ?? '',
        ];

        // Adicionar PurchaseRequest (Apenas para compra)
        if ($fields['transactionCode'] == self::TRANSACTION_TYPE_PURCHASE) {
            if (empty($prequest->billingData)) {
                throw new \InvalidArgumentException(
                    "Transação do tipo COMPRA PurchasePayment requer dados de cobrança (billing). " .
                        "Use o método \$paymentRequest->setBilling(...) antes de chamar renderPaymentForm()."
                );
            }
            try {
                $fields['purchaseRequest'] = $this->generatePurchaseRequest($prequest->billingData);
            } catch (\InvalidArgumentException $e) {
                throw $e;
            } catch (\Exception $e) {
                error_log("Erro interno ao gerar PurchaseRequest: " . $e->getMessage());
            }
        }

        $fields['fingerprint'] = $this->generateRequestFingerPrint($fields);

        $postUrl = $this->endpoint .
            "?FingerPrint=" . urlencode($fields["fingerprint"]) .
            "&TimeStamp=" . urlencode($fields["timeStamp"]) .
            "&FingerPrintVersion=" . urlencode($fields["fingerprintversion"]);

        return [
            'postUrl' => $postUrl,
            'fields' => $fields
        ];
    }

    private function formatUserBillingData(array $u)
    {
        $fmtDate = function ($ts) {
            return (empty($ts)) ? null : date('Ymd', is_numeric($ts) ? $ts : strtotime($ts));
        };
        $user = [
            'acctID'           => $u['id'] ?? null,
            'email'            => $u['email'] ?? null,
            'billAddrCountry'  => $u['country'] ?? null,
            'billAddrCity'     => $u['city'] ?? null,
            'billAddrLine1'    => $u['address'] ?? $u['address1'] ?? null,
            'billAddrLine2'    => $u['address2'] ?? null,
            'billAddrLine3'    => $u['address3'] ?? null,
            'billAddrPostCode' => $u['postCode'] ?? null,
            'mobilePhone' => [
                "cc" => isset($u['phone']) ? "138" : null, 
                "subscriber" => $u['phone'] ?? null 
            ],
            'acctInfo' => [
                'chAccAgeInd'           => $u['chAccAgeInd'] ?? '05',
                'chAccDate'             => $fmtDate($u['created_at'] ?? null),
                'chAccChange'           => $fmtDate($u['updated_at'] ?? null),
                'chAccPwChange'         => $fmtDate($u['pw_changed_at'] ?? null),
                'chAccPwChangeInd'      => $u['chAccPwChangeInd'] ?? '05',
                'suspiciousAccActivity' => $u['suspicious'] ?? '01',
            ],
        ];
        // 🔹 remove campos vazios recursivamente
        $clean = function ($arr) use (&$clean) {
            return array_filter($arr, function ($v) {
                return !is_null($v) && $v !== '';
            }, ARRAY_FILTER_USE_BOTH);
        };
        $user = $clean($user);
        if (isset($user['acctInfo'])) {
            $user['acctInfo'] = $clean($user['acctInfo']);
        }
        if (isset($user['mobilePhone'])) {
            $user['mobilePhone'] = $clean($user['acctInfo']);
        }
        return $user;
    }

    // ----------------------------------------------------------------------
    // PUBLIC: Métodos de Criação de Pagamento
    // ----------------------------------------------------------------------

    /**
     ** Cria e retorna o objeto de requisição para uma transação do tipo 'Compra' (TransactionCode='1').
     * O 'billingData' deve ser definido separadamente usando o método setBillings() no objeto retornado.
     *
     * @param float $amount Valor da transação (ex: 1000).
     * @param string $responseUrl URL de callback.
     * @param array{
     *  merchantRef?:string, merchantSession?:string, languageMessages?:string, currency?:string,
     * user?:array{
     * 
     * }
     * } $extra Outras opções (merchantRef, merchantSession, languageMessages, currency, user etc.).
     * @return PaymentRequest O objeto DTO pronto para ter o billingData adicionado e ser renderizado.
     */
    public function createPurchasePayment(float $amount, string $responseUrl, array $extra = []): PaymentRequest
    {
        $prequest = new PaymentRequest(
            $amount,
            $responseUrl,
            self::TRANSACTION_TYPE_PURCHASE
        );

        // Se o array 'user' for passado no $extra, ele é armazenado no billingData.
        // Isso é útil para fins de debug e contexto, mesmo que não seja o 3DS final.
        if (isset($extra['user']) && is_array($extra['user'])) {
            $user = $this->formatUserBillingData($extra['user']);
            // 🔹 mescla com billingData existente
            $prequest->billingData = array_merge($prequest->billingData, $user);
        }


        $prequest->merchantRef = $extra['merchantRef'] ?? ('R' . date('YmdHis'));
        $prequest->merchantSession = $extra['merchantSession'] ?? ('S' . date('YmdHis'));
        $prequest->languageMessages = $extra['languageMessages'] ?? 'pt';
        $prequest->currency = $extra['currency'] ?? self::CURRENCY_CVE;

        return $prequest;
    }

    /**
     ** Cria e retorna o objeto de requisição para uma transação do tipo 'Pagamento de Serviço' (TransactionCode='2').
     *
     * @param float $amount Valor da transação.
     * @param string $responseUrl URL de callback.
     * @param string $entityCode Código da Entidade.
     * @param string $referenceNumber Número de Referência.
     * @param array{
     *  merchantRef?:string, merchantSession?:string, languageMessages?:string, currency?:string
     * } $extra Outras opções (merchantRef, merchantSession, languageMessages, currency etc.).
     * @return PaymentRequest O objeto DTO pronto para ser renderizado.
     */
    public function createServicePayment(float $amount, string $responseUrl, string $entityCode, string $referenceNumber, array $extra = []): PaymentRequest
    {
        $prequest = new PaymentRequest(
            $amount,
            $responseUrl,
            self::TRANSACTION_TYPE_SERVICE_PAYMENT
        );
        $prequest->entityCode = $entityCode;
        $prequest->referenceNumber = $referenceNumber;

        $prequest->merchantRef = $extra['merchantRef'] ?? ('R' . date('YmdHis'));
        $prequest->merchantSession = $extra['merchantSession'] ?? ('S' . date('YmdHis'));
        $prequest->languageMessages = $extra['languageMessages'] ?? 'pt';
        $prequest->currency = $extra['currency'] ?? self::CURRENCY_CVE;

        return $prequest;
    }

    /**
     ** Cria e retorna o objeto de requisição para uma transação do tipo 'Recarga' (TransactionCode='3').
     *
     * @param float $amount Valor da transação.
     * @param string $responseUrl URL de callback.
     * @param string $entityCode Código da Entidade (operadora).
     * @param string $referenceNumber Número de Referência (telemóvel).
     * @param array{
     *  merchantRef?:string, merchantSession?:string, languageMessages?:string, currency?:string
     * } $extra Outras opções (merchantRef, merchantSession, languageMessages, currency etc.).
     * @return PaymentRequest O objeto DTO pronto para ser renderizado.
     */
    public function createRechargePayment(float $amount, string $responseUrl, string $entityCode, string $referenceNumber, array $extra = []): PaymentRequest
    {
        $prequest = new PaymentRequest(
            $amount,
            $responseUrl,
            self::TRANSACTION_TYPE_RECHARGE
        );
        $prequest->entityCode = $entityCode;
        $prequest->referenceNumber = $referenceNumber;

        $prequest->merchantRef = $extra['merchantRef'] ?? ('R' . date('YmdHis'));
        $prequest->merchantSession = $extra['merchantSession'] ?? ('S' . date('YmdHis'));
        $prequest->languageMessages = $extra['languageMessages'] ?? 'pt';
        $prequest->currency = $extra['currency'] ?? self::CURRENCY_CVE;

        return $prequest;
    }

    /**
     ** Prepara os dados da transação, gera o payload 3DS e o fingerprint de envio.
     * Este método retorna um array com a URL de destino (postUrl) e todos os campos (fields)
     * necessários para a submissão via POST, encapsulando a lógica de 'prepararPagamento'.
     *
     * @param PaymentRequest $prequest O objeto DTO contendo todos os dados necessários.
     * @return array{postUrl:string, fields:array} Contém as chaves 'postUrl' (URL de destino) e 'fields' (campos para o formulário POST).
     * @throws \InvalidArgumentException Se os campos obrigatórios do 'billingData' estiverem incompletos para o tipo 'Compra'.
     */
    protected function createPayment(PaymentRequest $prequest): array
    {
        // Garantir que a hora está no formato correto (Y-m-d H:i:s)
        $dateTime = date('Y-m-d H:i:s');

        // O Vinti4Net requer o valor em milésimos (multiplicado por 1000)
        $amountInMille = (int)round((float)$prequest->amount * 1000);

        // Valores Padrão/Default
        $fields = [
            'transactionCode' => $prequest->transactionCode,
            'posID' => $this->posID,
            'merchantRef' => $prequest->merchantRef,
            'merchantSession' => $prequest->merchantSession,
            'amount' => $amountInMille, // Valor já em milésimos
            'currency' => self::CURRENCY_CVE,
            'is3DSec' => '1',
            'urlMerchantResponse' => $prequest->responseUrl,
            'languageMessages' => $prequest->languageMessages,
            'timeStamp' => $dateTime,
            'fingerprintversion' => '1',
            'entityCode' => $prequest->entityCode ?? '',
            'referenceNumber' => $prequest->referenceNumber ?? '',
        ];

        // 2. Adicionar PurchaseRequest (Apenas para compra)
        if ($fields['transactionCode'] == self::TRANSACTION_TYPE_PURCHASE && !empty($prequest->billingData)) {
            try {
                $fields['purchaseRequest'] = $this->generatePurchaseRequest($prequest->billingData);
            } catch (\InvalidArgumentException $e) {
                // Re-lançar a exceção de validação de dados de billing
                throw $e;
            } catch (\Exception $e) {
                // Logar outros erros internos na geração do PurchaseRequest
                error_log("Erro ao gerar PurchaseRequest: " . $e->getMessage());
                // Não re-lançar: a transação prossegue com risco no 3DS, como no código original
            }
        }

        // GERAR FINGERPRINT
        $fields['fingerprint'] = $this->generateRequestFingerPrint($fields);

        // Montar a URL de POST (com os 3 parâmetros iniciais na query string)
        $postUrl = $this->endpoint .
            "?FingerPrint=" . urlencode($fields["fingerprint"]) .
            "&TimeStamp=" . urlencode($fields["timeStamp"]) .
            "&FingerPrintVersion=" . urlencode($fields["fingerprintversion"]);

        return [
            'postUrl' => $postUrl,
            'fields' => $fields
        ];
    }

    /**
     ** Renderiza um formulário HTML de auto-submissão para o gateway.
     * Processa o objeto de requisição e retorna o HTML de um formulário que se auto-submete para o Vinti4Net.
     * @param PaymentRequest $prequest O objeto DTO (com dados de billing, se necessário) a ser processado.
     * @return string O código HTML.
     * @throws \InvalidArgumentException Se os dados de billing estiverem ausentes para o tipo 'Compra'.
     */
    public function renderPaymentForm(PaymentRequest $prequest): string
    {
        // 1. Processa o DTO em campos POST (usando o método privado)
        $paymentData = $this->processPaymentRequest($prequest);

        // 2. Converte os campos em HTML
        $htmlFields = '';
        foreach ($paymentData['fields'] as $key => $value) {
            $htmlFields .= "<input type='hidden' name='" . htmlspecialchars($key) . "' value='" . htmlspecialchars($value) . "'>";
        }

        // 3. Retorna o formulário de auto-submissão
        return "
        <html>
            <head>
                <title>Pagamento Vinti4Net</title>
            </head>
            <body onload='document.forms[0].submit()'>
                <div>
                    <h5>Processando o pagamento... Por favor, aguarde.</h5>
                    <form action='{$paymentData['postUrl']}' method='post'>
                        {$htmlFields}
                    </form>
                </div>
            </body>
        </html>
        ";
    }

    // ----------------------------------------------------------------------
    // PUBLIC: Métodos de Callback (Resposta)
    // ----------------------------------------------------------------------

    /**
     ** Valida e interpreta a resposta enviada pelo Vinti4Net (Callback/Notificação),
     * retornando um objeto PaymentResult para acesso padronizado.
     *
     * @param array $postData Normalmente $_POST.
     * @return PaymentResult O objeto de resultado com o status, mensagem e dados da transação.
     */
    public function processResponse(array $postData): PaymentResult
    {
        // NOVO: Processamento e Anexação do DCC no início para garantir que esteja em todos os retornos.
        $dccData = [];
        if (!empty($postData['merchantRespDCCData'])) {
            // Tenta decodificar os dados DCC, assumindo que são um JSON
            $decoded = json_decode($postData['merchantRespDCCData'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $dccData = $decoded;
            } else {
                // Logar erro se DCCData estiver presente mas for inválido
                error_log("DCC Data inválido recebido: " . $postData['merchantRespDCCData']);
            }
        }
        // Anexa o array DCC decodificado (vazio ou preenchido) ao array de dados principal.
        $postData['dcc'] = $dccData;


        // 1. Cancelamento pelo Utilizador
        if (isset($postData["UserCancelled"]) && $postData["UserCancelled"] == "true") {
            return new PaymentResult(
                'CANCELLED',
                'Utilizador cancelou a requisição de compra no gateway.',
                false,
                $postData
            );
        }

        // 2. Transação com Resposta de Sucesso Potencial (messageType + merchantResp = C)
        // Assume que self::SUCCESS_MESSAGE_TYPES = ['8', 'P', 'M']
        $isSuccessMessageType = isset($postData["messageType"]) && in_array($postData["messageType"], self::SUCCESS_MESSAGE_TYPES);
        $isCompletedStatus = ($postData["merchantResp"] ?? '') === 'C';

        if ($isCompletedStatus && $isSuccessMessageType) {

            // CALCULAR FINGERPRINT
            $fingerPrintCalculado = $this->generateSuccessfulResponseFingerPrint($postData);
            $fingerPrintRecebido = $postData["resultFingerPrint"] ?? '';

            // VALIDAÇÃO CRÍTICA DE SEGURANÇA (usando hash_equals para evitar timing attacks)
            if (hash_equals($fingerPrintRecebido, $fingerPrintCalculado)) {
                // SUCESSO com Fingerprint Válido
                return new PaymentResult(
                    'SUCCESS',
                    'Transação bem-sucedida e Fingerprint Válido.',
                    true,
                    $postData
                );
            } else {
                // SUCESSO LÓGICO, MAS FALHA DE SEGURANÇA NO FINGERPRINT
                return new PaymentResult(
                    'FINGERPRINT_INVALIDO',
                    'ERRO CRÍTICO: Finger Print de Resposta Inválida. A transação pode ter ocorrido, mas a segurança falhou.',
                    false,
                    $postData,
                    [
                        'recebido' => $fingerPrintRecebido,
                        'calculado' => $fingerPrintCalculado
                    ]
                );
            }
        }

        // 3. Erro ou Falha Geral na Transação
        $message = $postData["merchantRespErrorDescription"] ?? 'Erro desconhecido na transação.';
        $detail = $postData["merchantRespErrorDetail"] ?? '';

        // Verifica se houve uma falha de autorização (messageType '6') ou outro erro explícito.
        $statusKey = (
            ($postData["messageType"] ?? '') === '6' ||
            !empty($postData["merchantRespErrorDescription"])
        ) ? 'FAILURE' : 'ERROR'; // 'FAILURE' = Recusa pelo banco; 'ERROR' = Erro de comunicação/sistema

        return new PaymentResult(
            $statusKey,
            $message . (!empty($detail) ? " Detalhe: {$detail}" : ''),
            false,
            $postData
        );
    }
}
