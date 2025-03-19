# WHMCS Register Clave - Plugin Elementor

Plugin WordPress que adiciona um widget do Elementor para integração com o formulário de registro do WHMCS.

## Requisitos

- WordPress 5.0 ou superior
- Elementor 3.0 ou superior
- PHP 7.4 ou superior
- WHMCS com API configurada
- Campo customizado "CPF/CNPJ" criado no WHMCS

## Instalação

1. Faça o upload da pasta `whmcs-register-clave` para o diretório `/wp-content/plugins/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Configure as credenciais da API do WHMCS no arquivo `process.php`:
```php
$whmcs_url = 'https://seu-whmcs.com/includes/api.php';
$identifier = 'seu-identifier';
$secret = 'seu-secret';
```

## Uso

1. Edite uma página com o Elementor
2. Procure pelo widget "WHMCS Register Form"
3. Arraste o widget para sua página
4. Configure as opções do widget se necessário
5. Publique/Atualize a página

## Recursos

### Widget Elementor
- Campos personalizáveis
- Estilos personalizáveis
- Integração com temas WordPress
- Responsivo

### Formulário
- Campos obrigatórios
- Máscaras para CNPJ e telefone
- Validação em tempo real
- Feedback visual
- Processamento AJAX

### Campos Incluídos
- Nome
- Sobrenome
- CNPJ
- Email
- Telefone

## Personalização

### Estilos CSS
O plugin inclui estilos básicos que podem ser sobrescritos. Os principais seletores são:

```css
.whmcs-register-form { /* Contêiner do formulário */ }
.whmcs-register-form .form-control { /* Campos de entrada */ }
.whmcs-register-form .btn-primary { /* Botão de envio */ }
.whmcs-register-form .alert { /* Mensagens de feedback */ }
```

### Filtros WordPress
O plugin fornece filtros para personalizar seu comportamento:

```php
add_filter('whmcs_register_form_fields', 'customize_fields');
add_filter('whmcs_register_form_messages', 'customize_messages');
```

## Solução de Problemas

### Logs
Os logs são salvos no arquivo de log padrão do WordPress. Para habilitar o debug:

1. Adicione ao wp-config.php:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

2. Verifique os logs em `/wp-content/debug.log`

### Erros Comuns

1. "Plugin não aparece no Elementor"
   - Verifique se o Elementor está instalado e ativo
   - Desative e reative o plugin

2. "Erro ao processar formulário"
   - Verifique as credenciais da API
   - Confirme se o campo CPF/CNPJ existe no WHMCS
   - Verifique os logs do WordPress

3. "Caracteres especiais incorretos"
   - Verifique o encoding do WordPress
   - Confirme que o banco de dados está em UTF-8

## Segurança

- Validação de dados
- Sanitização de entrada
- Proteção contra CSRF
- Comunicação segura com WHMCS

## Suporte

Para suporte técnico ou relatar problemas:
1. Abra uma issue no repositório
2. Entre em contato com a equipe de desenvolvimento

## Changelog

### 1.0.0
- Lançamento inicial
- Integração com Elementor
- Formulário de registro WHMCS
- Campos customizados
- Estilos responsivos
