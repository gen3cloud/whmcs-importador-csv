<?php
/**
 * Gerenciador de importação em lotes
 * 
 * Esta classe gerencia o processamento de grandes volumes de dados,
 * dividindo o CSV em lotes menores e controlando o processo de importação.
 */
class ImportManager {
    private $api;
    private $logFile;
    private $throttleDelay = 0.5; // Meio segundo entre requisições de API
    
    /**
     * Construtor
     */
    public function __construct($apiUrl, $apiIdentifier, $apiSecret) {
        $this->api = new WHMCSAPI($apiUrl, $apiIdentifier, $apiSecret);
        
        // Configura o arquivo de log
        $this->logFile = __DIR__ . '/logs/import_' . date('Ymd_His') . '.log';
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }
    
    /**
     * Testa a conexão com a API
     *
     * @return bool Retorna true se a conexão for bem sucedida
     */
    public function testApiConnection() {
        return $this->api->testConnection();
    }
    
    /**
     * Conta o número de linhas em um arquivo CSV (aproximado)
     *
     * @param string $csvFile Caminho para o arquivo CSV
     * @return int Número de linhas
     */
    public function countCsvLines($csvFile) {
        $lineCount = 0;
        $handle = fopen($csvFile, 'r');
        
        if ($handle) {
            while (!feof($handle)) {
                $line = fgets($handle);
                $lineCount++;
            }
            fclose($handle);
        }
        
        return $lineCount;
    }
    
    /**
     * Processa um lote de registros do CSV
     *
     * @param string $csvFile Caminho para o arquivo CSV
     * @param int $startLine Linha inicial (1 para o cabeçalho)
     * @param int $batchSize Tamanho do lote
     * @return array Resultados do processamento
     */
    public function processCsvBatch($csvFile, $startLine, $batchSize) {
        $results = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => []
        ];
        
        // Abre o arquivo CSV
        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            throw new Exception("Não foi possível abrir o arquivo CSV: {$csvFile}");
        }
        
        // Lê o cabeçalho
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new Exception("Falha ao ler o cabeçalho do CSV");
        }
        
        // Avança até a linha inicial do lote
        if ($startLine > 2) { // 2 porque já lemos o cabeçalho (linha 1)
            $currentLine = 2;
            while ($currentLine < $startLine && ($row = fgetcsv($handle)) !== false) {
                $currentLine++;
            }
        }
        
        // Processa os registros do lote
        $processedCount = 0;
        while ($processedCount < $batchSize && ($row = fgetcsv($handle)) !== false) {
            $lineNumber = $startLine + $processedCount;
            $processedCount++;
            $results['total']++;
            
            // Verifica se o número de colunas coincide com o cabeçalho
            if (count($row) != count($headers)) {
                $results['failed']++;
                $results['details'][] = [
                    'line' => $lineNumber,
                    'status' => 'failed',
                    'email' => 'N/A',
                    'message' => "Número de colunas não coincide com o cabeçalho"
                ];
                $this->log("ERRO: Linha {$lineNumber} - Número de colunas não coincide com o cabeçalho");
                continue;
            }
            
            // Combina o cabeçalho com os valores
            $clientData = array_combine($headers, $row);
            
            // Valida dados básicos
            if (empty($clientData['email']) || !filter_var($clientData['email'], FILTER_VALIDATE_EMAIL)) {
                $results['failed']++;
                $results['details'][] = [
                    'line' => $lineNumber,
                    'status' => 'failed',
                    'email' => $clientData['email'] ?? 'N/A',
                    'message' => "Email inválido ou vazio"
                ];
                $this->log("ERRO: Linha {$lineNumber} - Email inválido ou vazio: " . ($clientData['email'] ?? 'N/A'));
                continue;
            }
            
            if (empty($clientData['firstname']) || empty($clientData['lastname'])) {
                $results['failed']++;
                $results['details'][] = [
                    'line' => $lineNumber,
                    'status' => 'failed',
                    'email' => $clientData['email'],
                    'message' => "Nome ou sobrenome vazio"
                ];
                $this->log("ERRO: Linha {$lineNumber} - Nome ou sobrenome vazio: " . $clientData['email']);
                continue;
            }
            
            // Adiciona AMBOS formatos do campo personalizado (exatamente como na landing page)
            // Verifica qual campo está disponível no CSV: cnpj ou cpf_cnpj
            $documento = null;
            
            if (isset($clientData['cpf_cnpj']) && !empty($clientData['cpf_cnpj'])) {
                $documento = $clientData['cpf_cnpj'];
                $this->log("DEBUG: Usando campo 'cpf_cnpj' do CSV");
            } elseif (isset($clientData['cnpj']) && !empty($clientData['cnpj'])) {
                $documento = $clientData['cnpj'];
                $this->log("DEBUG: Usando campo 'cnpj' do CSV");
            }
            
            if ($documento) {
                // PASSO 1: Remover formatação (deixar apenas números)
                $cpf_cnpj = preg_replace('/[^0-9]/', '', $documento);
                $this->log("DEBUG: CPF/CNPJ após limpeza: $cpf_cnpj");
                
                // PASSO 2: Adicionar EXATAMENTE como no process.php da landing page
                
                // Formato direto (como na linha 100 do process.php)
                $clientParams['customfield1'] = $cpf_cnpj;
                
                // Formato de array (como nas linhas 103-113 do process.php)
                $customFieldValues = array();
                $customFieldValues['customfield'][1] = $cpf_cnpj;
                
                // Processa o array exatamente como na landing page
                foreach ($customFieldValues as $key => $value) {
                    if (is_array($value)) {
                        foreach ($value as $subkey => $subvalue) {
                            $clientParams[$key . '[' . $subkey . ']'] = $subvalue;
                        }
                    } else {
                        $clientParams[$key] = $value;
                    }
                }
                
                // Registra os parâmetros para diagnóstico
                $this->log("DEBUG: Parâmetros de cliente com campo CPF/CNPJ: " . 
                   json_encode(array_filter($clientParams, function($k) {
                       return strpos($k, 'custom') === 0;
                   }, ARRAY_FILTER_USE_KEY))
                );
            }
            
            try {
                // Verifica se o cliente já existe
                if ($this->api->clientExists($clientData['email'])) {
                    $results['skipped']++;
                    $results['details'][] = [
                        'line' => $lineNumber,
                        'status' => 'skipped',
                        'email' => $clientData['email'],
                        'message' => "Cliente já existe no WHMCS"
                    ];
                    $this->log("IGNORADO: Linha {$lineNumber} - Cliente já existe: " . $clientData['email']);
                    continue;
                }
                
                // Prepara os parâmetros para a API
                $clientParams = [
                    'firstname' => $clientData['firstname'],
                    'lastname' => $clientData['lastname'],
                    'email' => $clientData['email'],
                    'companyname' => $clientData['company'] ?? '',
                    'address1' => $clientData['address1'] ?? '',
                    'address2' => $clientData['address2'] ?? '',
                    'city' => $clientData['city'] ?? '',
                    'state' => $clientData['state'] ?? '',
                    'postcode' => $clientData['postcode'] ?? '',
                    'country' => $clientData['country'] ?? 'BR',
                    'phonenumber' => $clientData['phonenumber'] ?? '',
                    'password2' => $clientData['password'] ?? '',
                    'notes' => $clientData['notes'] ?? 'Importado via CSV em lote',
                    'skipvalidation' => true
                ];
                
                // Controle de taxa de requisições (throttling)
                usleep($this->throttleDelay * 1000000);
                
                // Cria o cliente no WHMCS
                $response = $this->api->createClient($clientParams);
                
                // Verifica a resposta
                if ($response['result'] == 'success') {
                    $results['success']++;
                    $results['details'][] = [
                        'line' => $lineNumber,
                        'status' => 'success',
                        'email' => $clientData['email'],
                        'message' => "Cliente criado com sucesso (ID: " . $response['clientid'] . ")"
                    ];
                    $this->log("SUCESSO: Linha {$lineNumber} - Cliente criado: " . $clientData['email'] . " (ID: " . $response['clientid'] . ")");
                } else {
                    $results['failed']++;
                    $results['details'][] = [
                        'line' => $lineNumber,
                        'status' => 'failed',
                        'email' => $clientData['email'],
                        'message' => "Erro: " . $response['message']
                    ];
                    $this->log("ERRO: Linha {$lineNumber} - " . $clientData['email'] . " - " . $response['message']);
                }
            } catch (Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'line' => $lineNumber,
                    'status' => 'failed',
                    'email' => $clientData['email'] ?? 'N/A',
                    'message' => "Exceção: " . $e->getMessage()
                ];
                $this->log("EXCEÇÃO: Linha {$lineNumber} - " . ($clientData['email'] ?? 'N/A') . " - " . $e->getMessage());
            }
        }
        
        fclose($handle);
        return $results;
    }
    
    /**
     * Registra uma mensagem no log
     *
     * @param string $message Mensagem a ser registrada
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
}
