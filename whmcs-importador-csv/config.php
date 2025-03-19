<?php
/**
 * Configurações para o Importador CSV para WHMCS
 */

// Configurações da API WHMCS
$config = [
    // URL da API (deve terminar com /includes/api.php)
    'whmcs_url' => 'https://cliente.claveinternet.com.br/includes/api.php',
    
    // Credenciais de API
    'identifier' => '3UOVmg8BPQ1KdUfiEjp0b2OBB3Q3Pp6K',
    'secret' => 'QmRc0OKczFa0OuLfyRJXqo0gw0HqRsmD',
    
    // Aliases para backward compatibility
    'whmcs_identifier' => '3UOVmg8BPQ1KdUfiEjp0b2OBB3Q3Pp6K',
    'whmcs_secret' => 'QmRc0OKczFa0OuLfyRJXqo0gw0HqRsmD',
    
    // Tamanho do lote para importação em massa (registros por lote)
    'batch_size' => 50,
    
    // Configurações de upload
    'max_upload_size' => 10 * 1024 * 1024, // 10MB
    'allowed_extensions' => ['csv'],
    
    // Diretório para logs
    'log_dir' => __DIR__ . '/logs',
    
    // Diretório para arquivos temporários
    'temp_dir' => __DIR__ . '/temp',
    
    // Diretório para checkpoints de importação
    'checkpoint_dir' => __DIR__ . '/checkpoints',
    
    // Ativar modo de debug (true/false)
    'debug' => true,
    
    // Ignorar verificação SSL (apenas para desenvolvimento/teste)
    'ignore_ssl' => true
];

// Criar diretórios necessários se não existirem
foreach (['log_dir', 'temp_dir', 'checkpoint_dir'] as $dir) {
    if (!file_exists($config[$dir])) {
        mkdir($config[$dir], 0755, true);
    }
}

// Define constantes para compatibilidade com código legado
define('MAX_UPLOAD_SIZE', $config['max_upload_size']);
define('LOG_DIR', $config['log_dir']);
define('TEMP_DIR', $config['temp_dir']);
define('DEBUG_MODE', $config['debug']);
define('LOG_ERRORS', true);
define('LOG_FILE', $config['log_dir'] . '/import.log');
define('ALLOWED_EXTENSIONS', $config['allowed_extensions']);
define('WHMCS_URL', $config['whmcs_url']);
define('WHMCS_IDENTIFIER', $config['identifier']);
define('WHMCS_SECRET', $config['secret']);
