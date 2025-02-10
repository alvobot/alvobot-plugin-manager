<?php
if (!defined('ABSPATH')) {
    exit;
}

$fonts = $this->get_available_fonts();
$icons = $this->get_available_icons();
?>

<div class="wrap alvobot-pro-wrap">
    <div class="alvobot-pro-header">
        <h1>Gerador de Logo - AlvoBot Pro</h1>
        <p>Configure e gere o logo e favicon do seu site automaticamente.</p>
    </div>

    <div class="alvobot-pro-notices"></div>

    <div class="alvobot-pro-logo-generator">
        <div class="logo-generator-form">
            <form method="post" action="" id="logo-generator-form">
                <?php wp_nonce_field('logo_generator_nonce', 'logo_nonce'); ?>
                
                <div class="form-group">
                    <label for="blog_name">Nome do Blog</label>
                    <input type="text" id="blog_name" name="blog_name" value="<?php echo esc_attr(get_bloginfo('name')); ?>" class="regular-text" required>
                </div>

                <div class="form-group">
                    <label for="font_choice">Fonte</label>
                    <select id="font_choice" name="font_choice" required>
                        <?php foreach ($fonts as $key => $font): ?>
                            <option value="<?php echo esc_attr($key); ?>" style="font-family: <?php echo esc_attr($font['family']); ?>">
                                <?php echo esc_html($font['name']); ?> - <?php echo esc_html($font['description']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="font_color">Cor da Fonte</label>
                    <input type="text" id="font_color" name="font_color" value="#000000" class="color-picker">
                </div>

                <div class="form-group">
                    <label for="background_color">Cor do Fundo</label>
                    <input type="text" id="background_color" name="background_color" value="#FFFFFF" class="color-picker">
                </div>

                <div class="form-group">
                    <label>Ícone</label>
                    <div class="icon-grid">
                        <?php foreach ($icons as $icon): ?>
                            <div class="icon-option">
                                <input type="radio" name="icon_choice" value="<?php echo esc_attr($icon['path']); ?>" id="icon_<?php echo esc_attr($icon['name']); ?>" required>
                                <label for="icon_<?php echo esc_attr($icon['name']); ?>">
                                    <img src="<?php echo esc_url($icon['url']); ?>" alt="<?php echo esc_attr($icon['name']); ?>">
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </div>

        <div class="logo-preview-section">
            <div id="preview-container"></div>
            <div class="button-group">
                <button class="button button-primary" id="save-logo">Salvar Logo</button>
                <button class="button" id="regenerate-logo">Gerar Novo</button>
            </div>
        </div>
    </div>
</div>