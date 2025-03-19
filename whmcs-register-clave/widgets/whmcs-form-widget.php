<?php
class WHMCS_Form_Widget extends \Elementor\Widget_Base {
    public function get_name() {
        return 'whmcs_register_form';
    }

    public function get_title() {
        return 'WHMCS Register Form';
    }

    public function get_icon() {
        return 'eicon-form-horizontal';
    }

    public function get_categories() {
        return ['general'];
    }

    protected function register_controls() {
        // Seção de Estilo dos Campos
        $this->start_controls_section(
            'section_fields_style',
            [
                'label' => 'Estilo dos Campos',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'fields_typography',
                'selector' => '{{WRAPPER}} .form-control',
            ]
        );

        $this->add_control(
            'fields_background_color',
            [
                'label' => 'Cor de Fundo',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .form-control' => 'background-color: {{VALUE}}',
                ],
                'default' => '#ffffff',
            ]
        );

        $this->end_controls_section();

        // Seção de Estilo do Botão
        $this->start_controls_section(
            'section_button_style',
            [
                'label' => 'Estilo do Botão',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'button_text_color',
            [
                'label' => 'Cor do Texto',
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .btn-primary' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'button_background_color',
            [
                'label' => 'Cor de Fundo',
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#0d6efd',
                'selectors' => [
                    '{{WRAPPER}} .btn-primary' => 'background-color: {{VALUE}}; border-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        ?>
        <div class="whmcs-register-form">
            <div id="alertMessage" style="display: none;" class="alert" role="alert"></div>
            
            <form id="whmcsForm" class="needs-validation" novalidate>
                <div class="row g-3">
                    <!-- Nome -->
                    <div class="col-sm-6">
                        <label for="firstname" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="firstname" name="firstname" required>
                        <div class="invalid-feedback">
                            Nome é obrigatório.
                        </div>
                    </div>

                    <!-- Sobrenome -->
                    <div class="col-sm-6">
                        <label for="lastname" class="form-label">Sobrenome</label>
                        <input type="text" class="form-control" id="lastname" name="lastname" required>
                        <div class="invalid-feedback">
                            Sobrenome é obrigatório.
                        </div>
                    </div>

                    <!-- CNPJ -->
                    <div class="col-12">
                        <label for="cpf_cnpj" class="form-label">CNPJ</label>
                        <input type="text" class="form-control" id="cpf_cnpj" name="cpf_cnpj" required>
                        <div class="invalid-feedback">
                            CNPJ é obrigatório.
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="col-12">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                        <div class="invalid-feedback">
                            Email válido é obrigatório.
                        </div>
                    </div>

                    <!-- Telefone -->
                    <div class="col-12">
                        <label for="phone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="phone" name="phone" required>
                        <div class="invalid-feedback">
                            Telefone é obrigatório.
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <button class="w-100 btn btn-primary btn-lg" type="submit">Cadastrar</button>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Máscaras para os campos
            $('#phone').mask('(00) 00000-0000');
            $('#cpf_cnpj').mask('00.000.000/0000-00');

            // Manipula o envio do formulário
            $('#whmcsForm').on('submit', function(e) {
                e.preventDefault();
                
                if (this.checkValidity()) {
                    const $form = $(this);
                    const $submitButton = $form.find('button[type="submit"]');
                    const $alert = $('#alertMessage');
                    
                    // Desabilita o botão durante o envio
                    $submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enviando...');
                    
                    // Envia os dados para o process.php
                    $.ajax({
                        url: '<?php echo home_url("?whmcs-register-process"); ?>',
                        method: 'POST',
                        data: $form.serialize(),
                        dataType: 'json'
                    })
                    .done(function(response) {
                        // Mostra a mensagem de sucesso ou erro
                        $alert
                            .removeClass('alert-success alert-danger')
                            .addClass(response.success ? 'alert-success' : 'alert-danger')
                            .html(response.message)
                            .show();
                        
                        // Se foi sucesso, limpa o formulário
                        if (response.success) {
                            $form[0].reset();
                            $form.removeClass('was-validated');
                        }
                    })
                    .fail(function(jqXHR, textStatus, errorThrown) {
                        console.error('Erro na requisição:', textStatus, errorThrown);
                        $alert
                            .removeClass('alert-success')
                            .addClass('alert-danger')
                            .html('Erro ao processar a requisição. Tente novamente.')
                            .show();
                    })
                    .always(function() {
                        // Reativa o botão
                        $submitButton.prop('disabled', false).html('Cadastrar');
                    });
                }
                
                $(this).addClass('was-validated');
            });
        });
        </script>
        <?php
    }
}
