<?php
/**
 * Script para transferência do sistema para servidor remoto via SFTP
 * Isso é apenas um modelo para você personalizar com suas credenciais de servidor
 */

// Define as configurações de conexão
$server_settings = [
    'host' => 'seu_servidor.com',
    'port' => 22,
    'username' => 'seu_usuario',
    'password' => 'sua_senha',
    'remote_path' => '/caminho/no/servidor/whmcs-importador-csv/',
    'local_file' => __DIR__ . '/../whmcs-importador-csv.zip'
];

// Template de instruções para inserir no servidor
$setup_instructions = <<<EOT
#!/bin/bash
# Script de configuração no servidor remoto

# Descompactar o arquivo
unzip -o whmcs-importador-csv.zip

# Mover os arquivos para o diretório correto
mkdir -p {$server_settings['remote_path']}
mv projetos/whmcs-importador-csv/* {$server_settings['remote_path']}

# Configurar permissões
chmod 755 {$server_settings['remote_path']}
chmod 755 {$server_settings['remote_path']}logs
chmod 755 {$server_settings['remote_path']}temp
chmod 755 {$server_settings['remote_path']}checkpoints

# Limpeza
rm -rf projetos
echo "Configuração concluída!"
EOT;

// Exibe instruções para upload manual
echo "============================================\n";
echo "INSTRUÇÕES PARA UPLOAD MANUAL\n";
echo "============================================\n\n";

echo "1. Faça upload do arquivo whmcs-importador-csv.zip para seu servidor usando FTP/SFTP\n";
echo "2. Conecte-se ao servidor via SSH e execute os seguintes comandos:\n\n";

echo $setup_instructions;

echo "\n\n";
echo "3. Acesse o sistema pelo navegador em: http://seu_servidor.com/caminho/para/whmcs-importador-csv/\n";
echo "4. Execute primeiro o arquivo check_environment.php para verificar o ambiente\n";
echo "5. Em seguida, teste a conexão com a API usando test_api.php\n\n";

echo "============================================\n";
echo "TRANSFERÊNCIA AUTOMÁTICA VIA SFTP (PHP)\n";
echo "============================================\n\n";

echo "Para usar este script de transferência automática:\n";
echo "1. Edite este arquivo e preencha as configurações do seu servidor\n";
echo "2. Execute o script pelo terminal: php upload_to_server.php\n\n";

echo "IMPORTANTE: Este script é apenas um modelo. Você precisa personalizá-lo com suas próprias credenciais de servidor.\n";

// Função para transferir via SFTP (apenas um exemplo)
function transfer_via_sftp($settings) {
    if (!function_exists('ssh2_connect')) {
        echo "ERRO: A extensão SSH2 do PHP não está instalada.\n";
        return false;
    }
    
    echo "Conectando ao servidor {$settings['host']}:{$settings['port']}...\n";
    
    // Estabelece conexão SSH
    $connection = ssh2_connect($settings['host'], $settings['port']);
    if (!$connection) {
        echo "ERRO: Falha ao conectar ao servidor.\n";
        return false;
    }
    
    // Autentica
    if (!ssh2_auth_password($connection, $settings['username'], $settings['password'])) {
        echo "ERRO: Falha na autenticação.\n";
        return false;
    }
    
    // Inicia sessão SFTP
    $sftp = ssh2_sftp($connection);
    if (!$sftp) {
        echo "ERRO: Falha ao iniciar sessão SFTP.\n";
        return false;
    }
    
    // Verifica se o diretório remoto existe
    $remote_path = $settings['remote_path'];
    if (!file_exists("ssh2.sftp://{$sftp}{$remote_path}")) {
        // Tenta criar o diretório
        if (!ssh2_sftp_mkdir($sftp, $remote_path, 0755, true)) {
            echo "ERRO: Falha ao criar diretório remoto.\n";
            return false;
        }
    }
    
    // Faz upload do arquivo
    $local_file = $settings['local_file'];
    $remote_file = $remote_path . basename($local_file);
    
    echo "Enviando {$local_file} para {$remote_file}...\n";
    
    if (!ssh2_scp_send($connection, $local_file, $remote_file, 0644)) {
        echo "ERRO: Falha ao enviar arquivo.\n";
        return false;
    }
    
    echo "Arquivo enviado com sucesso!\n";
    
    // Executa script de configuração
    echo "Executando script de configuração...\n";
    $stream = ssh2_exec($connection, "cd {$remote_path} && " . $setup_instructions);
    stream_set_blocking($stream, true);
    $output = stream_get_contents($stream);
    echo $output;
    
    return true;
}

// Comentado para evitar execução acidental - remova este comentário quando estiver pronto para usar
// if ($server_settings['host'] !== 'seu_servidor.com') {
//     transfer_via_sftp($server_settings);
// } else {
//     echo "Edite as configurações do servidor antes de executar a transferência automática.\n";
// }

echo "\n============================================\n";
