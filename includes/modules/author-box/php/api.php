<?php

if (!defined('ABSPATH')) {
    exit;
}

class AlvoBotPro_AuthorBox_API {
    private static $instance = null;
    private $namespace = 'alvobot-pro/v1';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/authors/(?P<username>[\w-]+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_author_info'),
            'permission_callback' => array($this, 'verify_token'),
            'args' => array(
                'token' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'username' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'display_name' => array(
                    'required' => false,
                    'type' => 'string',
                ),
                'description' => array(
                    'required' => false,
                    'type' => 'string',
                ),
                'author_image' => array(
                    'required' => false,
                    'type' => 'string',
                ),
            ),
        ));
    }

    public function verify_token($request) {
        $json = $request->get_json_params();
        $token = isset($json['token']) ? $json['token'] : null;
        
        if (!$token) {
            return new WP_Error(
                'missing_token',
                'Token não fornecido',
                array('status' => 401)
            );
        }
        
        // Verifica se o token é válido consultando a opção do WordPress
        $stored_token = get_option('grp_site_token');
        
        if (!$stored_token || $token !== $stored_token) {
            return new WP_Error(
                'invalid_token',
                'Token inválido',
                array('status' => 401)
            );
        }

        return true;
    }

    public function update_author_info($request) {
        $json = $request->get_json_params();
        
        $token = $json['token'];
        $username = $json['username'];
        $display_name = isset($json['display_name']) ? $json['display_name'] : null;
        $description = isset($json['description']) ? $json['description'] : null;
        $author_image = isset($json['author_image']) ? $json['author_image'] : null;

        // Obtém o usuário pelo username
        $user = get_user_by('login', $username);
        
        if (!$user) {
            return new WP_Error(
                'invalid_user',
                'Usuário não encontrado',
                array('status' => 404)
            );
        }

        $response = array();

        // Atualiza o nome de exibição
        if ($display_name) {
            wp_update_user(array(
                'ID' => $user->ID,
                'display_name' => sanitize_text_field($display_name)
            ));
            $response['display_name'] = 'Nome de exibição atualizado com sucesso';
        }

        // Atualiza a descrição
        if ($description) {
            update_user_meta($user->ID, 'description', wp_kses_post($description));
            $response['description'] = 'Descrição atualizada com sucesso';
        }

        // Atualiza a imagem do autor se fornecida como URL base64
        if ($author_image) {
            // Decodifica a imagem base64
            $image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $author_image));
            
            if ($image_data === false) {
                return new WP_Error(
                    'invalid_image',
                    'Formato de imagem inválido',
                    array('status' => 400)
                );
            }

            // Cria um arquivo temporário
            $upload_dir = wp_upload_dir();
            $filename = wp_unique_filename($upload_dir['path'], 'author-image.jpg');
            $filepath = $upload_dir['path'] . '/' . $filename;

            // Salva o arquivo
            if (file_put_contents($filepath, $image_data)) {
                $wp_filetype = wp_check_filetype($filename, null);
                
                $attachment = array(
                    'post_mime_type' => $wp_filetype['type'],
                    'post_title' => sanitize_file_name($filename),
                    'post_content' => '',
                    'post_status' => 'inherit'
                );

                // Insere o attachment
                $attach_id = wp_insert_attachment($attachment, $filepath);
                
                if (!is_wp_error($attach_id)) {
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
                    wp_update_attachment_metadata($attach_id, $attach_data);
                    
                    // Salva o ID da imagem na meta do usuário
                    update_user_meta($user->ID, 'ab_custom_avatar_id', $attach_id);
                    $response['author_image'] = 'Imagem atualizada com sucesso';
                } else {
                    return new WP_Error(
                        'attachment_error',
                        'Erro ao processar a imagem',
                        array('status' => 400)
                    );
                }
            } else {
                return new WP_Error(
                    'upload_error',
                    'Erro ao salvar a imagem',
                    array('status' => 400)
                );
            }
        }

        if (empty($response)) {
            return new WP_Error(
                'no_changes',
                'Nenhuma alteração foi solicitada',
                array('status' => 400)
            );
        }

        return new WP_REST_Response($response, 200);
    }
}

// Inicializa a API
add_action('init', array('AlvoBotPro_AuthorBox_API', 'get_instance'));
