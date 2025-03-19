<?php
/**
 * Teste da conexão com a API do WHMCS
 */

// Inicializa buffer de saída
ob_start();

// Ativa exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carrega as configurações e classes
require_once 'config.php';
require_once 'WHMCSAPI.php';

// Função para formatar resultados
function formatResults($data) {
    if (!is_array($data)) {
        return htmlspecialchars($data);
    }
    
    $result = '';
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $result .= "<strong>$key</strong>: " . formatResults($value) . "<br>";
        } else {
            $result .= "<strong>$key</strong>: " . htmlspecialchars($value) . "<br>";
        }
    }
    return $result;
}

// Realiza os testes
$testResults = [];

// Cria instância da API
$api = new WHMCSAPI();

// Status da conexão
$connectionTest = [
    'name' => 'Conexão com a API',
    'success' => false,
    'details' => []
];

// Tenta executar uma ação simples
$result = $api->call([
    'action' => 'GetSystemURL'
]);

if ($result) {
    $connectionTest['success'] = true;
    $connectionTest['details']['URL do sistema'] = $result['systemurl'] ?? 'N/A';
    
    // Tenta obter versão do WHMCS
    $versionResult = $api->call(['action' => 'GetVersion']);
    if ($versionResult) {
        $connectionTest['details']['Versão do WHMCS'] = $versionResult['whmcs_version'] ?? 'N/A';
    }
    
    // Verificando número de clientes
    $clientsResult = $api->call(['action' => 'GetClientsCount']);
    if ($clientsResult) {
        $connectionTest['details']['Total de clientes'] = $clientsResult['totalresults'] ?? 'N/A';
    }
} else {
    $connectionTest['success'] = false;
    $connectionTest['error'] = $api->getLastError();
    
    // Lista os arquivos de log disponíveis
    $logDir = $config['log_dir'];
    $logFileContent = '';
    
    if (file_exists($logDir)) {
        $logFiles = glob($logDir . '/api_*.log');
        if (!empty($logFiles)) {
            // Obtém o log mais recente
            $latestLog = $logFiles[count($logFiles) - 1];
            $logFileContent = file_get_contents($latestLog);
            $connectionTest['logFile'] = basename($latestLog);
        }
    }
    
    $connectionTest['logContent'] = $logFileContent;
}

$testResults[] = $connectionTest;

// Teste de verificação de cliente
$clientTest = [
    'name' => 'Verificação de Cliente Existente',
    'success' => false,
    'details' => []
];

// Testa a verificação de cliente existente
$testEmail = "teste@exemplo.com";
$clientTest['details']['Email testado'] = $testEmail;

if ($api->clientExists($testEmail)) {
    $clientTest['success'] = true;
    $clientTest['details']['Resultado'] = 'Cliente encontrado';
} else {
    if ($api->getLastError()) {
        $clientTest['success'] = false;
        $clientTest['error'] = $api->getLastError();
    } else {
        $clientTest['success'] = true;
        $clientTest['details']['Resultado'] = 'Cliente não encontrado';
    }
}

$testResults[] = $clientTest;

// Verifica modo de saída
if (php_sapi_name() === 'cli') {
    // Saída para linha de comando
    echo "=================================================\n";
    echo "Teste de Conexão com a API do WHMCS\n";
    echo "=================================================\n\n";

    // Exibe as configurações (sem exibir todo o secret)
    echo "URL da API: " . $config['whmcs_url'] . "\n";
    echo "Identificador: " . $config['identifier'] . "\n";
    echo "Secret: " . substr($config['secret'], 0, 3) . '***' . substr($config['secret'], -3) . "\n";
    echo "Ignorar SSL: " . ($config['ignore_ssl'] ? 'Sim' : 'Não') . "\n";
    echo "Modo Debug: " . ($config['debug'] ? 'Ativado' : 'Desativado') . "\n\n";

    foreach ($testResults as $test) {
        echo "=================================================\n";
        echo "{$test['name']}: " . ($test['success'] ? "SUCESSO" : "FALHA") . "\n";
        echo "=================================================\n";
        
        if (isset($test['error'])) {
            echo "Erro: {$test['error']}\n\n";
            
            if (isset($test['logContent']) && !empty($test['logContent'])) {
                echo "Conteúdo do log mais recente ({$test['logFile']}):\n";
                echo "----------------------------------------\n";
                echo $test['logContent'] . "\n";
                echo "----------------------------------------\n";
            }
            
            if ($test['name'] === 'Conexão com a API') {
                echo "\nSugestões para solução de problemas:\n";
                echo "1. Verifique se o URL da API está correto\n";
                echo "2. Verifique se as credenciais da API estão corretas\n";
                echo "3. Certifique-se que a API está habilitada no WHMCS\n";
                echo "4. Verifique se há restrições de IP para acessar a API\n";
                echo "5. Confira se o servidor WHMCS está online e funcionando\n";
                echo "6. Verifique os logs para mais detalhes sobre o erro\n";
            }
        } else {
            foreach ($test['details'] as $key => $value) {
                echo "- $key: $value\n";
            }
        }
        echo "\n";
    }
} else {
    // Saída para navegador
    ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de API - Importador CSV para WHMCS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        body {
            padding: 30px;
            background-color: #f8f9fa;
        }
        .header {
            margin-bottom: 30px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 20px;
        }
        .test-card {
            margin-bottom: 25px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .card-header {
            font-weight: 600;
        }
        .status-success {
            color: #28a745;
        }
        .status-error {
            color: #dc3545;
        }
        .result-icon {
            font-size: 1.2rem;
            margin-right: 10px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        .config-card {
            background-color: #f8f9fa;
            border-left: 4px solid #17a2b8;
        }
        pre {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            max-height: 300px;
            overflow: auto;
        }
        .badge-config {
            font-size: 85%;
            font-weight: normal;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-plug mr-3 text-primary"></i>Teste de API</h1>
            <h4 class="text-muted">Importador CSV para WHMCS</h4>
        </div>
        
        <!-- Configuration Info -->
        <div class="card mb-4 config-card">
            <div class="card-body">
                <h5><i class="fas fa-cog mr-2"></i>Configuração da API</h5>
                <table class="table table-sm">
                    <tbody>
                        <tr>
                            <td width="150"><strong>URL da API:</strong></td>
                            <td><?php echo htmlspecialchars($config['whmcs_url']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Identificador:</strong></td>
                            <td><?php echo htmlspecialchars($config['identifier']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Secret:</strong></td>
                            <td><?php echo substr($config['secret'], 0, 3) . '***' . substr($config['secret'], -3); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Ignorar SSL:</strong></td>
                            <td>
                                <span class="badge <?php echo $config['ignore_ssl'] ? 'badge-warning' : 'badge-success'; ?> badge-config">
                                    <?php echo $config['ignore_ssl'] ? 'Sim' : 'Não'; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Modo Debug:</strong></td>
                            <td>
                                <span class="badge <?php echo $config['debug'] ? 'badge-info' : 'badge-secondary'; ?> badge-config">
                                    <?php echo $config['debug'] ? 'Ativado' : 'Desativado'; ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Test Results -->
        <div class="row">
            <?php foreach ($testResults as $test): ?>
                <div class="col-md-6 mb-4">
                    <div class="card test-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas <?php echo $test['name'] === 'Conexão com a API' ? 'fa-link' : 'fa-user-check'; ?> mr-2"></i>
                                <?php echo $test['name']; ?>
                            </span>
                            <span class="<?php echo $test['success'] ? 'status-success' : 'status-error'; ?>">
                                <?php if ($test['success']): ?>
                                    <i class="fas fa-check-circle result-icon"></i>
                                    <span>Sucesso</span>
                                <?php else: ?>
                                    <i class="fas fa-times-circle result-icon"></i>
                                    <span>Falha</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <?php if (isset($test['error'])): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    <?php echo htmlspecialchars($test['error']); ?>
                                </div>
                                
                                <?php if ($test['name'] === 'Conexão com a API'): ?>
                                    <h6 class="mt-4 mb-2">Sugestões para solução:</h6>
                                    <ul class="small">
                                        <li>Verifique se o URL da API está correto</li>
                                        <li>Verifique se as credenciais da API estão corretas</li>
                                        <li>Certifique-se que a API está habilitada no WHMCS</li>
                                        <li>Verifique se há restrições de IP para acessar a API</li>
                                        <li>Confira se o servidor WHMCS está online e funcionando</li>
                                        <li>Verifique os logs para mais detalhes sobre o erro</li>
                                    </ul>
                                <?php endif; ?>
                                
                                <?php if (isset($test['logContent']) && !empty($test['logContent'])): ?>
                                    <div class="mt-3">
                                        <a class="btn btn-sm btn-outline-secondary" data-toggle="collapse" href="#logContent" role="button">
                                            <i class="fas fa-file-alt mr-1"></i>
                                            Ver Log (<?php echo $test['logFile']; ?>)
                                        </a>
                                        <div class="collapse mt-2" id="logContent">
                                            <pre class="small"><?php echo htmlspecialchars($test['logContent']); ?></pre>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($test['details'] as $key => $value): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span><?php echo htmlspecialchars($key); ?></span>
                                            <span class="badge badge-primary badge-pill"><?php echo htmlspecialchars($value); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="footer text-center mt-4">
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home mr-1"></i>
                Voltar para a página inicial
            </a>
            <a href="check_environment.php" class="btn btn-info ml-2">
                <i class="fas fa-tasks mr-1"></i>
                Verificar Ambiente
            </a>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
    <?php
}

// Encerra o buffer de saída
ob_end_flush();
?>
