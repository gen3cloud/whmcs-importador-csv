<?php
/**
 * Script para importação de clientes a partir de arquivo CSV
 */

// Inicia a sessão e buffer de saída (para evitar problema de headers already sent)
ob_start();
session_start();

// Ativa exibição de erros para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Carrega as configurações e classes
require_once 'config.php';
require_once 'WHMCSAPI.php';
require_once 'CSVProcessor.php';

// Define o diretório de logs
$logDir = $config['log_dir'];
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// Arquivo de log
$logFile = $logDir . '/import_' . date('Ymd_His') . '.log';

// Função para fazer log
function logMessage($message) {
    global $logFile, $config;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}" . PHP_EOL, FILE_APPEND);
    
    // Se estamos debugando, também imprime na tela
    if (isset($config['debug']) && $config['debug']) {
        echo "[{$timestamp}] {$message}<br>";
    }
}

// Inicializa a API do WHMCS
$api = new WHMCSAPI($config['whmcs_url'], $config['identifier'], $config['secret']);

// Verifica se foi enviado um arquivo
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != UPLOAD_ERR_OK) {
    $errorMsg = 'Erro ao enviar o arquivo. Código: ' . ($_FILES['csv_file']['error'] ?? 'Arquivo não enviado');
    logMessage($errorMsg);
    $_SESSION['error_message'] = $errorMsg;
    header('Location: index.php');
    exit;
}

// Verifica o tamanho do arquivo
if ($_FILES['csv_file']['size'] > $config['max_upload_size']) {
    $errorMsg = 'O arquivo excede o tamanho máximo permitido (' . ($config['max_upload_size'] / (1024*1024)) . 'MB)';
    logMessage('Erro durante a importação: ' . $errorMsg);
    $_SESSION['error_message'] = $errorMsg;
    header('Location: index.php');
    exit;
}

// Verifica o tipo de arquivo
$fileInfo = finfo_open(FILEINFO_MIME_TYPE);
$fileType = finfo_file($fileInfo, $_FILES['csv_file']['tmp_name']);
finfo_close($fileInfo);

if ($fileType !== 'text/csv' && $fileType !== 'text/plain' && $fileType !== 'application/vnd.ms-excel') {
    $errorMsg = 'Tipo de arquivo inválido. Envie um arquivo CSV válido.';
    logMessage($errorMsg);
    $_SESSION['error_message'] = $errorMsg;
    header('Location: index.php');
    exit;
}

// Move o arquivo enviado para o diretório temporário
$tempDir = $config['temp_dir'];
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0755, true);
}

$tempFile = $tempDir . '/' . basename($_FILES['csv_file']['name']);
if (!move_uploaded_file($_FILES['csv_file']['tmp_name'], $tempFile)) {
    $errorMsg = 'Falha ao mover o arquivo enviado.';
    logMessage($errorMsg);
    $_SESSION['error_message'] = $errorMsg;
    header('Location: index.php');
    exit;
}

// Processa o arquivo CSV
try {
    // Log do início do processamento
    logMessage('Iniciando processamento do arquivo: ' . basename($tempFile));
    logMessage('Tamanho do arquivo: ' . round($_FILES['csv_file']['size'] / 1024, 2) . 'KB');
    
    // ABORDAGEM SIMPLIFICADA: Processa diretamente o arquivo CSV
    // Isso evita o uso de métodos que podem não estar funcionando no servidor
    
    // Abre o arquivo
    $handle = fopen($tempFile, 'r');
    if (!$handle) {
        throw new Exception("Não foi possível abrir o arquivo para processamento");
    }
    
    // Lê o cabeçalho
    $headers = fgetcsv($handle, 0, ',', '"', '\\');
    if (!$headers) {
        fclose($handle);
        throw new Exception("Falha ao ler o cabeçalho do CSV");
    }
    
    // Inicializa contadores
    $totalClients = 0;
    $successCount = 0;
    $failedCount = 0;
    $skippedCount = 0;
    $results = [];
    
    // Lê e processa cada linha
    $lineNumber = 1; // Começa do 1 para contar o cabeçalho
    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $lineNumber++;
        $totalClients++;
        
        // Verifica se o número de colunas coincide
        if (count($row) != count($headers)) {
            logMessage("Erro na linha {$lineNumber}: número de colunas não coincide com o cabeçalho");
            continue;
        }
        
        // Combina cabeçalho e valores
        $client = array_combine($headers, $row);
        
        // Verifica campos obrigatórios
        $requiredFields = ['firstname', 'lastname', 'email'];
        $missingFields = false;
        foreach ($requiredFields as $field) {
            if (!isset($client[$field]) || empty(trim($client[$field]))) {
                logMessage("Linha {$lineNumber}: Campo obrigatório '{$field}' está vazio");
                $missingFields = true;
            }
        }
        
        if ($missingFields) {
            $failedCount++;
            $results[] = [
                'line' => $lineNumber,
                'email' => $client['email'] ?? 'Desconhecido',
                'status' => 'failed',
                'message' => 'Campos obrigatórios faltando'
            ];
            continue;
        }
        
        // Valida email
        if (!filter_var($client['email'], FILTER_VALIDATE_EMAIL)) {
            $failedCount++;
            $results[] = [
                'line' => $lineNumber,
                'email' => $client['email'],
                'status' => 'failed',
                'message' => 'Email inválido'
            ];
            logMessage("Linha {$lineNumber}: Email inválido '{$client['email']}'");
            continue;
        }
        
        // Verifica se o cliente já existe
        if ($api->clientExists($client['email'])) {
            $skippedCount++;
            $results[] = [
                'line' => $lineNumber,
                'email' => $client['email'],
                'status' => 'skipped',
                'message' => 'Cliente já existe no WHMCS'
            ];
            logMessage("Cliente {$client['email']} ignorado: já existe");
            continue;
        }
        
        // Adiciona uma senha aleatória se não foi fornecida
        if (!isset($client['password']) || empty($client['password'])) {
            $client['password'] = generatePassword();
            logMessage("Senha gerada automaticamente para {$client['email']}");
        }
        
        // Processar o CNPJ exatamente como na landing page (correção crítica)
        if (isset($client['cnpj']) && !empty($client['cnpj'])) {
            // Limpeza do CNPJ - apenas números (como na landing page)
            $cpf_cnpj = preg_replace('/[^0-9]/', '', $client['cnpj']);
            logMessage("Processando CNPJ: {$client['cnpj']} -> $cpf_cnpj");
            
            // EXATAMENTE como na landing page (process.php que funciona)
            $client['customfield1'] = $cpf_cnpj;
            
            // Formato de array (como nas linhas 103-113 do process.php da landing page)
            $customFieldValues = array();
            $customFieldValues['customfield'][1] = $cpf_cnpj;
            
            // Processa o array exatamente como na landing page
            foreach ($customFieldValues as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $subkey => $subvalue) {
                        $client[$key . '[' . $subkey . ']'] = $subvalue;
                    }
                } else {
                    $client[$key] = $value;
                }
            }
            
            // Log para diagnóstico
            logMessage("Detalhes de campos personalizados: " . json_encode(array_filter($client, function($k) {
                return strpos($k, 'custom') === 0;
            }, ARRAY_FILTER_USE_KEY)));
        }
        
        // Suporte para campo cpf_cnpj também (compatibilidade)
        elseif (isset($client['cpf_cnpj']) && !empty($client['cpf_cnpj'])) {
            // Limpeza do CPF/CNPJ - apenas números
            $cpf_cnpj = preg_replace('/[^0-9]/', '', $client['cpf_cnpj']);
            logMessage("Processando CPF/CNPJ: {$client['cpf_cnpj']} -> $cpf_cnpj");
            
            // EXATAMENTE como na landing page
            $client['customfield1'] = $cpf_cnpj;
            
            // Formato de array
            $customFieldValues = array();
            $customFieldValues['customfield'][1] = $cpf_cnpj;
            
            // Processa o array exatamente como na landing page
            foreach ($customFieldValues as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $subkey => $subvalue) {
                        $client[$key . '[' . $subkey . ']'] = $subvalue;
                    }
                } else {
                    $client[$key] = $value;
                }
            }
            
            // Log para diagnóstico
            logMessage("Detalhes de campos personalizados: " . json_encode(array_filter($client, function($k) {
                return strpos($k, 'custom') === 0;
            }, ARRAY_FILTER_USE_KEY)));
        }
        
        // Tenta criar o cliente
        $response = $api->createClient($client);
        
        if ($response) {
            $successCount++;
            $results[] = [
                'line' => $lineNumber,
                'email' => $client['email'],
                'status' => 'success',
                'message' => 'Cliente importado com sucesso'
            ];
            logMessage("Cliente {$client['email']} importado com sucesso");
        } else {
            $failedCount++;
            $results[] = [
                'line' => $lineNumber,
                'email' => $client['email'],
                'status' => 'failed',
                'message' => 'Erro: ' . $api->getLastError()
            ];
            logMessage("Erro ao importar cliente {$client['email']}: " . $api->getLastError());
        }
    }
    
    // Fecha o arquivo
    fclose($handle);
    
    // Armazena os resultados na sessão
    $_SESSION['import_results'] = [
        'total' => $totalClients,
        'success' => $successCount,
        'failed' => $failedCount,
        'skipped' => $skippedCount,
        'details' => $results
    ];
    
    // Remove o arquivo temporário
    unlink($tempFile);
    
    // Encerra o buffer de saída e redireciona
    ob_end_clean();
    header('Location: index.php?page=results');
    exit;
    
} catch (Exception $e) {
    logMessage('Erro ao processar o arquivo: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Erro ao processar o arquivo: ' . $e->getMessage();
    
    // Encerra o buffer de saída e redireciona
    ob_end_clean();
    header('Location: index.php');
    exit;
}

/**
 * Gera uma senha aleatória segura
 */
function generatePassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+;:,.?';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}
?>
