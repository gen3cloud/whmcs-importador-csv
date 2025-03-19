# Implantação do Importador CSV para WHMCS em Servidor Remoto

Este documento contém as instruções para implantar e configurar o sistema de importação em massa no servidor remoto.

## Passo 1: Upload dos Arquivos

1. Faça upload de todos os arquivos do diretório `/Users/vinicius/projetos/whmcs-importador-csv/` para o servidor remoto
2. Recomendação: coloque em um subdiretório como `/admin/tools/importador-csv/` ou similar
3. Certifique-se de que os arquivos mantenham a mesma estrutura

## Passo 2: Configuração de Permissões

As seguintes pastas precisam ter permissão de escrita pelo usuário do servidor web:

```bash
chmod 755 logs/
chmod 755 checkpoints/
chmod 755 temp/
```

Se as pastas não existirem, o sistema tentará criá-las, mas é recomendado criá-las manualmente:

```bash
mkdir -p logs checkpoints temp
chmod 755 logs checkpoints temp
```

## Passo 3: Configuração do PHP

Para processar grandes volumes de dados, certifique-se de que o PHP está configurado com limites adequados:

1. Adicione um arquivo `.htaccess` (para Apache) com estas configurações:

```
php_value upload_max_filesize 50M
php_value post_max_size 52M
php_value memory_limit 512M
php_value max_execution_time 300
php_value max_input_time 300
```

2. Ou modifique o php.ini no servidor:

```
upload_max_filesize = 50M
post_max_size = 52M
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
```

## Passo 4: Teste da API

1. Acesse o arquivo `test_api.php` pelo navegador para verificar a conexão com a API
2. Se houver problemas, verifique:
   - A URL da API está correta?
   - As credenciais estão corretas?
   - O servidor tem permissão para acessar a API do WHMCS?

## Passo 5: Processando Arquivos Grandes (50k registros)

Para processar arquivos muito grandes, use o comando CLI no servidor:

```bash
cd /caminho/para/importador
php batch_import.php arquivo.csv
```

Onde:
- `arquivo.csv` é o caminho para o arquivo CSV

Para executar em segundo plano (permitindo desconectar do servidor):

```bash
nohup php batch_import.php arquivo.csv > import_log.txt 2>&1 &
```

## Passo 6: Formatação correta do CNPJ no CSV

1. O arquivo CSV deve conter uma coluna chamada `cnpj`
2. O valor pode ser formatado (xx.xxx.xxx/xxxx-xx) ou apenas números (xxxxxxxxxxxxxxx)
3. O importador limpará automaticamente a formatação antes de enviar para o WHMCS
4. Para empresas, o campo CNPJ é obrigatório

Exemplo de linha correta no CSV:
```
firstname,lastname,email,company,cnpj
João,Silva,joao@empresa.com.br,Empresa ABC,12.345.678/0001-90
```

## Solução de Problemas

### Timeout durante a importação
- Verifique se o servidor WHMCS não está limitando requisições
- Use o modo CLI que ignora o limite de tempo de execução

### Erros de memória
- Aumente o limite de memória no php.ini ou .htaccess

### Problemas com CNPJ
- Verifique se o campo está corretamente nomeado como `cnpj` no arquivo CSV
- Se estiver tendo problemas, tente remover toda a formatação (pontos, barras, traços)
- Verifique os logs para ver como o CNPJ está sendo enviado para a API

### Logs
- Verifique a pasta `logs/` para detalhes sobre erros
- Os arquivos de log têm o formato `import_YYYYMMDD_HHMMSS.log`

## Segurança

**IMPORTANTE**: O diretório de importação contém credenciais da API do WHMCS. Recomendamos:

1. Proteja o diretório com autenticação HTTP básica
2. Use HTTPS para o acesso ao importador
3. Remova o sistema após o uso ou mantenha-o em uma área segura
