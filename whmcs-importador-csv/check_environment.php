<?php
/**
 * Script de verificação de ambiente
 * Verifica requisitos, configurações e permissões
 */

// Impede acesso direto se incluído em outro arquivo
if (!defined('DIRECT_ACCESS')) {
    define('DIRECT_ACCESS', true);
}

// Inicializa buffer de saída
ob_start();

// Pastas necessárias
$requiredDirs = [
    'logs',
    'temp',
    'checkpoints'
];

// Função para verificar e criar diretórios
function checkAndCreateDirectory($dir) {
    $fullPath = __DIR__ . '/' . $dir;
    $status = '';
    $isOk = false;
    
    if (!file_exists($fullPath)) {
        if (mkdir($fullPath, 0755, true)) {
            $status = "CRIADO COM SUCESSO";
            $isOk = true;
        } else {
            $status = "FALHA AO CRIAR";
            $isOk = false;
        }
    } else {
        if (is_writable($fullPath)) {
            $status = "JÁ EXISTE (Gravável)";
            $isOk = true;
        } else {
            $status = "JÁ EXISTE (Sem permissão de escrita)";
            $isOk = false;
        }
    }
    
    return [
        'directory' => $dir,
        'status' => $status,
        'ok' => $isOk
    ];
}

// Função para verificar a versão do PHP
function checkPhpVersion() {
    $minVersion = '7.0.0';
    $currentVersion = phpversion();
    
    if (version_compare($currentVersion, $minVersion, '>=')) {
        $status = "OK";
        $isOk = true;
    } else {
        $status = "ABAIXO DO RECOMENDADO";
        $isOk = false;
    }
    
    return [
        'current' => $currentVersion,
        'required' => $minVersion,
        'status' => $status,
        'ok' => $isOk
    ];
}

// Função para verificar extensões do PHP
function checkPhpExtensions() {
    $requiredExtensions = [
        'curl',
        'json',
        'fileinfo'
    ];
    
    $results = [];
    $allOk = true;
    
    foreach ($requiredExtensions as $ext) {
        $isLoaded = extension_loaded($ext);
        $results[] = [
            'extension' => $ext,
            'loaded' => $isLoaded,
            'status' => $isLoaded ? 'OK' : 'NÃO ENCONTRADA'
        ];
        
        if (!$isLoaded) {
            $allOk = false;
        }
    }
    
    return [
        'extensions' => $results,
        'ok' => $allOk
    ];
}

// Função para verificar configurações PHP
function checkPhpSettings() {
    $recommendedSettings = [
        'memory_limit' => '256M',
        'upload_max_filesize' => '50M',
        'post_max_size' => '50M',
        'max_execution_time' => '300',
        'max_input_time' => '300'
    ];
    
    $results = [];
    
    foreach ($recommendedSettings as $setting => $recommendedValue) {
        $currentValue = ini_get($setting);
        
        // Converte para bytes para comparação
        $currentBytes = convertToBytes($currentValue);
        $recommendedBytes = convertToBytes($recommendedValue);
        
        if ($setting == 'memory_limit' || $setting == 'upload_max_filesize' || $setting == 'post_max_size') {
            $isOk = $currentBytes >= $recommendedBytes;
        } else {
            $isOk = $currentValue >= $recommendedValue;
        }
        
        $results[] = [
            'setting' => $setting,
            'current' => $currentValue,
            'recommended' => $recommendedValue,
            'status' => $isOk ? 'OK' : 'ABAIXO DO RECOMENDADO',
            'ok' => $isOk
        ];
    }
    
    return $results;
}

// Função para converter valores como '256M' para bytes
function convertToBytes($value) {
    $value = trim($value);
    $lastChar = strtolower($value[strlen($value)-1]);
    $num = (int)$value;
    
    switch($lastChar) {
        case 'g': $num *= 1024;
        case 'm': $num *= 1024;
        case 'k': $num *= 1024;
    }
    
    return $num;
}

// Executa as verificações
$phpVersion = checkPhpVersion();
$phpExtensions = checkPhpExtensions();
$phpSettings = checkPhpSettings();

$directories = [];
$allDirsOk = true;
foreach ($requiredDirs as $dir) {
    $result = checkAndCreateDirectory($dir);
    $directories[] = $result;
    if (!$result['ok']) {
        $allDirsOk = false;
    }
}

// Verifica config.php
$configExists = file_exists(__DIR__ . '/config.php');

// Determina status geral
$allChecksOk = $phpVersion['ok'] && $phpExtensions['ok'] && $allDirsOk && $configExists;

// Para requisições CLI, gera saída em texto plano
if (php_sapi_name() === 'cli') {
    // Cabeçalho
    echo "=================================================\n";
    echo "Verificação de Ambiente - Importador CSV para WHMCS\n";
    echo "=================================================\n\n";

    // Verifica versão do PHP
    echo "Verificando versão do PHP... ";
    echo "Atual: {$phpVersion['current']}, Mínima recomendada: {$phpVersion['required']}... ";
    echo "{$phpVersion['status']}\n\n";

    // Verifica extensões do PHP
    echo "Verificando extensões do PHP necessárias:\n";
    foreach ($phpExtensions['extensions'] as $ext) {
        echo "  - {$ext['extension']}... {$ext['status']}\n";
    }
    echo "\n";

    // Verifica configurações do PHP
    echo "Verificando configurações do PHP:\n";
    foreach ($phpSettings as $setting) {
        echo "  - {$setting['setting']}: Atual = {$setting['current']}, Recomendado = {$setting['recommended']}... {$setting['status']}\n";
    }
    echo "\n";

    // Verifica e cria diretórios
    echo "Verificando diretórios necessários:\n";
    foreach ($directories as $dir) {
        echo "  - Verificando diretório: {$dir['directory']}... {$dir['status']}\n";
    }

    // Testa acesso ao arquivo de configuração
    echo "\nVerificando arquivo de configuração... ";
    echo $configExists ? "ENCONTRADO" : "NÃO ENCONTRADO";
    echo "\n";

    // Conclusão
    echo "\n=================================================\n";
    echo $allChecksOk 
        ? "AMBIENTE PREPARADO: O sistema está pronto para uso.\n"
        : "ATENÇÃO: Existem questões que precisam ser resolvidas antes de usar o sistema.\n";
    echo "=================================================\n";
} 
// Para requisições web, gera saída HTML formatada
else {
    // HTML para saída formatada
    ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Ambiente - Importador CSV para WHMCS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
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
        .check-card {
            margin-bottom: 25px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .card-header {
            font-weight: 600;
        }
        .status-ok {
            color: #28a745;
        }
        .status-warning {
            color: #ffc107;
        }
        .status-error {
            color: #dc3545;
        }
        .result-icon {
            font-size: 1.2rem;
            margin-right: 5px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        .big-status {
            font-size: 1.2rem;
            font-weight: bold;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .verdict {
            text-align: center;
            padding: 20px;
            margin-top: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Verificação de Ambiente</h1>
            <h4 class="text-muted">Importador CSV para WHMCS</h4>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <!-- PHP Version -->
                <div class="card check-card">
                    <div class="card-header bg-light">
                        Versão do PHP
                    </div>
                    <div class="card-body">
                        <p><strong>Versão atual:</strong> <?php echo $phpVersion['current']; ?></p>
                        <p><strong>Versão mínima recomendada:</strong> <?php echo $phpVersion['required']; ?></p>
                        <div class="mt-3 <?php echo $phpVersion['ok'] ? 'status-ok' : 'status-warning'; ?>">
                            <i class="result-icon"><?php echo $phpVersion['ok'] ? '✓' : '⚠️'; ?></i>
                            <?php echo $phpVersion['status']; ?>
                        </div>
                    </div>
                </div>
                
                <!-- PHP Extensions -->
                <div class="card check-card">
                    <div class="card-header bg-light">
                        Extensões do PHP
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Extensão</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($phpExtensions['extensions'] as $ext): ?>
                                <tr>
                                    <td><?php echo $ext['extension']; ?></td>
                                    <td class="<?php echo $ext['loaded'] ? 'status-ok' : 'status-error'; ?>">
                                        <i class="result-icon"><?php echo $ext['loaded'] ? '✓' : '✗'; ?></i>
                                        <?php echo $ext['status']; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <!-- PHP Settings -->
                <div class="card check-card">
                    <div class="card-header bg-light">
                        Configurações do PHP
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Configuração</th>
                                    <th>Atual</th>
                                    <th>Recomendado</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($phpSettings as $setting): ?>
                                <tr>
                                    <td><?php echo $setting['setting']; ?></td>
                                    <td><?php echo $setting['current']; ?></td>
                                    <td><?php echo $setting['recommended']; ?></td>
                                    <td class="<?php echo $setting['ok'] ? 'status-ok' : 'status-warning'; ?>">
                                        <i class="result-icon"><?php echo $setting['ok'] ? '✓' : '⚠️'; ?></i>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Directories -->
                <div class="card check-card">
                    <div class="card-header bg-light">
                        Diretórios Necessários
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Diretório</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($directories as $dir): ?>
                                <tr>
                                    <td><?php echo $dir['directory']; ?></td>
                                    <td class="<?php echo $dir['ok'] ? 'status-ok' : 'status-error'; ?>">
                                        <i class="result-icon"><?php echo $dir['ok'] ? '✓' : '✗'; ?></i>
                                        <?php echo $dir['status']; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Config File -->
                <div class="card check-card">
                    <div class="card-header bg-light">
                        Arquivo de Configuração
                    </div>
                    <div class="card-body">
                        <div class="<?php echo $configExists ? 'status-ok' : 'status-error'; ?>">
                            <i class="result-icon"><?php echo $configExists ? '✓' : '✗'; ?></i>
                            <?php echo $configExists ? 'ENCONTRADO' : 'NÃO ENCONTRADO'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Final Verdict -->
        <div class="verdict <?php echo $allChecksOk ? 'bg-success text-white' : 'bg-warning'; ?>">
            <?php if ($allChecksOk): ?>
                <h4 class="mb-0">AMBIENTE PREPARADO: O sistema está pronto para uso.</h4>
            <?php else: ?>
                <h4 class="mb-0">ATENÇÃO: Existem questões que precisam ser resolvidas antes de usar o sistema.</h4>
            <?php endif; ?>
        </div>
        
        <div class="footer text-center mt-4">
            <a href="index.php" class="btn btn-primary">Voltar para a página inicial</a>
        </div>
    </div>
</body>
</html>
    <?php
}

// Se foi chamado diretamente (não incluído)
if (defined('DIRECT_ACCESS') && DIRECT_ACCESS) {
    ob_end_flush();
}
?>
