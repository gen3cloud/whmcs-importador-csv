<?php
// Configurações para exibir todos os erros
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Função para exibir detalhes da configuração do PHP
function debugInfo() {
    echo "<h1>Informações de Debug</h1>";
    
    echo "<h2>Versão do PHP</h2>";
    echo "<pre>" . phpversion() . "</pre>";
    
    echo "<h2>Extensões Carregadas</h2>";
    echo "<pre>";
    print_r(get_loaded_extensions());
    echo "</pre>";
    
    echo "<h2>Teste do arquivo config.php</h2>";
    if (file_exists('config.php')) {
        echo "O arquivo config.php existe.<br>";
        try {
            require_once 'config.php';
            echo "Config carregado com sucesso.<br>";
            echo "URL da API: " . $config['whmcs_url'] . "<br>";
            echo "Identifier: " . (isset($config['identifier']) ? 'definido' : 'não definido') . "<br>";
            echo "Secret: " . (isset($config['secret']) ? 'definido' : 'não definido') . "<br>";
        } catch (Exception $e) {
            echo "Erro ao carregar config.php: " . $e->getMessage();
        }
    } else {
        echo "O arquivo config.php não existe!";
    }
    
    echo "<h2>Teste da classe WHMCSAPI</h2>";
    if (file_exists('WHMCSAPI.php')) {
        echo "O arquivo WHMCSAPI.php existe.<br>";
        try {
            require_once 'WHMCSAPI.php';
            echo "Classe WHMCSAPI carregada com sucesso.<br>";
            $api = new WHMCSAPI();
            echo "Instância da classe WHMCSAPI criada com sucesso.<br>";
        } catch (Exception $e) {
            echo "Erro ao instanciar WHMCSAPI: " . $e->getMessage();
        }
    } else {
        echo "O arquivo WHMCSAPI.php não existe!";
    }
    
    echo "<h2>Verificação de permissões</h2>";
    $directoriesToCheck = ['logs', 'temp', 'checkpoints'];
    foreach ($directoriesToCheck as $dir) {
        if (file_exists($dir)) {
            echo "Diretório $dir existe. ";
            echo "Permissões: " . substr(sprintf('%o', fileperms($dir)), -4) . "<br>";
            echo "Gravável: " . (is_writable($dir) ? 'Sim' : 'Não') . "<br>";
        } else {
            echo "Diretório $dir não existe.<br>";
            echo "Tentando criar... ";
            if (mkdir($dir, 0755, true)) {
                echo "Sucesso!<br>";
            } else {
                echo "Falha!<br>";
            }
        }
    }
    
    echo "<h2>Verifica o acesso à API</h2>";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config['whmcs_url']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'identifier' => $config['identifier'],
        'secret' => $config['secret'],
        'action' => 'GetClients', // Usando uma ação válida com formato correto
        'responsetype' => 'json'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$config['ignore_ssl']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, !$config['ignore_ssl'] ? 2 : 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    
    if (curl_errno($ch)) {
        echo "Erro cURL: " . curl_error($ch) . "<br>";
    } else {
        echo "Código HTTP: " . $info['http_code'] . "<br>";
        echo "Tempo de resposta: " . $info['total_time'] . " segundos<br>";
        echo "Resposta:<br><pre>";
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            print_r($decoded);
        } else {
            echo htmlspecialchars($response);
        }
        echo "</pre>";
    }
    curl_close($ch);
}

// Executar diagnóstico
debugInfo();
?>
