<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
    <!-- wp:heading {"level":1} -->
    <h1><?php esc_html_e('Entre em Contato', 'alvobot-pro'); ?></h1>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"className":"intro"} -->
    <p class="intro"><?php esc_html_e('Estamos à disposição para atender você. Utilize os canais abaixo ou preencha o formulário.', 'alvobot-pro'); ?></p>
    <!-- /wp:paragraph -->

    <!-- wp:columns {"style":{"spacing":{"margin":{"top":"2rem","bottom":"2rem"}}}} -->
    <div class="wp-block-columns" style="margin-top:2rem;margin-bottom:2rem">
        <!-- wp:column -->
        <div class="wp-block-column">
            <!-- wp:heading -->
            <h2><?php esc_html_e('Canais de Atendimento', 'alvobot-pro'); ?></h2>
            <!-- /wp:heading -->

            <!-- wp:group {"className":"contact-info"} -->
            <div class="wp-block-group contact-info">
                <!-- wp:heading {"level":3} -->
                <h3><?php esc_html_e('Telefones', 'alvobot-pro'); ?></h3>
                <!-- /wp:heading -->

                <!-- wp:paragraph -->
                <p><strong><?php esc_html_e('Central de Atendimento:', 'alvobot-pro'); ?></strong><br>
                [company_phone]</p>
                <!-- /wp:paragraph -->

                <!-- wp:paragraph -->
                <p><strong><?php esc_html_e('WhatsApp:', 'alvobot-pro'); ?></strong><br>
                [company_whatsapp]</p>
                <!-- /wp:paragraph -->

                <!-- wp:paragraph -->
                <p><strong><?php esc_html_e('Emergência 24h:', 'alvobot-pro'); ?></strong><br>
                [emergency_phone]</p>
                <!-- /wp:paragraph -->

                <!-- wp:heading {"level":3} -->
                <h3><?php esc_html_e('E-mails', 'alvobot-pro'); ?></h3>
                <!-- /wp:heading -->

                <!-- wp:paragraph -->
                <p><strong><?php esc_html_e('Suporte:', 'alvobot-pro'); ?></strong><br>
                [support_email]</p>
                <!-- /wp:paragraph -->

                <!-- wp:paragraph -->
                <p><strong><?php esc_html_e('Vendas:', 'alvobot-pro'); ?></strong><br>
                [sales_email]</p>
                <!-- /wp:paragraph -->

                <!-- wp:paragraph -->
                <p><strong><?php esc_html_e('Contato Geral:', 'alvobot-pro'); ?></strong><br>
                [contact_email]</p>
                <!-- /wp:paragraph -->

                <!-- wp:heading {"level":3} -->
                <h3><?php esc_html_e('Horários', 'alvobot-pro'); ?></h3>
                <!-- /wp:heading -->

                <!-- wp:paragraph -->
                <p><strong><?php esc_html_e('Atendimento Comercial:', 'alvobot-pro'); ?></strong><br>
                [working_hours_extended]</p>
                <!-- /wp:paragraph -->

                <!-- wp:paragraph -->
                <p><strong><?php esc_html_e('Suporte Técnico:', 'alvobot-pro'); ?></strong><br>
                [support_hours]</p>
                <!-- /wp:paragraph -->
            </div>
            <!-- /wp:group -->

            <!-- wp:heading -->
            <h2><?php esc_html_e('Redes Sociais', 'alvobot-pro'); ?></h2>
            <!-- /wp:heading -->

            <!-- wp:social-links {"iconColor":"base","iconColorValue":"#ffffff","iconBackgroundColor":"contrast","iconBackgroundColorValue":"#000000","style":{"spacing":{"blockGap":{"top":"10px","left":"10px"}}}} -->
            <ul class="wp-block-social-links has-icon-color has-icon-background-color">
                <!-- wp:social-link {"url":"[facebook_url]","service":"facebook"} /-->
                <!-- wp:social-link {"url":"[instagram_url]","service":"instagram"} /-->
                <!-- wp:social-link {"url":"[linkedin_url]","service":"linkedin"} /-->
                <!-- wp:social-link {"url":"[youtube_url]","service":"youtube"} /-->
            </ul>
            <!-- /wp:social-links -->

            <!-- wp:heading -->
            <h2><?php esc_html_e('Endereço', 'alvobot-pro'); ?></h2>
            <!-- /wp:heading -->

            <!-- wp:paragraph -->
            <p><strong>[company_legal_name]</strong><br>
            CNPJ: [company_document]<br>
            IE: [company_state_document]<br>
            [company_full_address]</p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:column -->

        <!-- wp:column -->
        <div class="wp-block-column">
            <!-- wp:heading -->
            <h2><?php esc_html_e('Formulário de Contato', 'alvobot-pro'); ?></h2>
            <!-- /wp:heading -->

            <!-- wp:paragraph {"className":"form-intro"} -->
            <p class="form-intro"><?php esc_html_e('Preencha o formulário abaixo e nossa equipe entrará em contato o mais breve possível.', 'alvobot-pro'); ?></p>
            <!-- /wp:paragraph -->

            <!-- wp:shortcode -->
            [alvobot_contact_form]
            <!-- /wp:shortcode -->
        </div>
        <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->

    <!-- wp:heading -->
    <h2><?php esc_html_e('Como Chegar', 'alvobot-pro'); ?></h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"className":"map-intro"} -->
    <p class="map-intro"><?php esc_html_e('Nossa sede está localizada em uma região de fácil acesso, próxima a importantes vias e ao metrô.', 'alvobot-pro'); ?></p>
    <!-- /wp:paragraph -->

    <!-- wp:html -->
    <div class="map-wrapper">
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3657.098839441408!2d-46.65529082375834!3d-23.564046261681632!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x94ce59c8da0aa315%3A0xd59f9431f2c9776a!2sAv.%20Paulista%2C%201000%20-%20Bela%20Vista%2C%20S%C3%A3o%20Paulo%20-%20SP%2C%2001310-100!5e0!3m2!1spt-BR!2sbr!4v1708037727646!5m2!1spt-BR!2sbr" 
            width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
    <!-- /wp:html -->

    <!-- wp:paragraph {"className":"map-instructions"} -->
    <p class="map-instructions"><?php esc_html_e('De metrô: Estação Paulista (Linha Verde) ou Consolação (Linha Amarela)', 'alvobot-pro'); ?><br>
    <?php esc_html_e('De ônibus: Diversas linhas param na Av. Paulista', 'alvobot-pro'); ?><br>
    <?php esc_html_e('De carro: Estacionamento no local com valet', 'alvobot-pro'); ?></p>
    <!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
