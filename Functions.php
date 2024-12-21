/******************************************************************************************************************************************************************
 *
 * NO MOVER LAS SIGUIENTES LINEAS, SON PARA DAR FUNCIONALIDAD A LA API DE AUTENTIFICACIÓN
 * FIRMADO: ESTEBAN SALGADO
 * 
 ******************************************************************************************************************************************************************/

add_action('rest_api_init', function () {
    register_rest_route('book_reader', '/auth/', array(
        'methods' => 'POST',
        'callback' => 'book_reader_authenticate_user',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('book_reader', '/get_user/', array(
        'methods' => 'GET',
        'callback' => 'book_reader_get_user_info',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('book_reader', '/books/', array(
        'methods' => 'GET',
        'callback' => 'book_reader_get_books',
        'permission_callback' => '__return_true',
    ));
});

function book_reader_authenticate_user($request) {
    $email = sanitize_email($request['email']);
    $password = sanitize_text_field($request['password']);
    $user = wp_authenticate($email, $password);

    if (is_wp_error($user)) {
        return new WP_REST_Response(['authenticated' => false], 401);
    }

    return new WP_REST_Response(['authenticated' => true], 200);
}

function book_reader_get_user_info($request) {
    $email = sanitize_email($request['email']);
    $user = get_user_by('email', $email);

    if (!$user) {
        return new WP_REST_Response(['error' => 'User not found'], 404);
    }

    return new WP_REST_Response([
        'username' => $user->user_login,
        'email' => $user->user_email,
    ], 200);
}

function book_reader_get_books($request) {
    $email = sanitize_email($request['email']);
    $user1 = get_user_by('email', $email); // Cambiado de $user1 a $user para mantener consistencia

    if (!$user1) {
        return new WP_REST_Response(['error' => 'User not found'], 404);
    }
	
	//var_dump($user1);
	//var_dump($request['email']);
	

    // Obtener pedidos del usuario y registrar la cantidad de pedidos encontrados
    $customer_orders = wc_get_orders(array(
        'customer_id' => $user1->ID,
        'status' => 'completed',
        'limit' => -1
    ));

    $purchased_books = [];

    // Itera sobre cada pedido para obtener los productos
    foreach ($customer_orders as $order) {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            if ($product) {
                // Obtener categorías del producto
                $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
                
                // Verificar si el producto está en la categoría "book"
                if (in_array('Books', $categories)) {
                    $purchased_books[] = [
                        'id' => $product->get_id(),
                        'name' => $product->get_name()
                    ];
                }
            }
        }
    }    
	//var_dump($purchased_books);

    // Si no se encontraron productos con la etiqueta "book"
    if (empty($purchased_books)) {
        return new WP_REST_Response(['error' => 'No books found'], 404);
    }

    return new WP_REST_Response([
        'purchased_products' => $purchased_books
    ], 200);
}



//******************************************************************************//
function block_language_slugs_direct_access() {
    // Obtener la ruta de la URL actual
    $uri = $_SERVER['REQUEST_URI'];
    $uri = rtrim($uri, '/');  // Limpiar la barra al final si existe
    $uri_segments = explode('/', trim($uri, '/'));

    // Lista de slugs a bloquear acceso directo
    $blocked_slugs = ['es', 'en'];

    // Verificar si el primer segmento de la URL está en la lista de bloqueados
    if (in_array($uri_segments[0], $blocked_slugs) && count($uri_segments) === 1) {
        // Mostrar la página 404 si solo se accede al slug bloqueado
        get_header();
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        get_template_part('template-parts/404');  // Usa el path correcto a la plantilla 404 dentro de template-parts
		get_footer();
        exit();
    }
}
add_action('template_redirect', 'block_language_slugs_direct_access');

function update_header_for_woocommerce_product_pages() {
    if (is_product()) {
        // Remueve los breadcrumbs
        remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
		
		//get_header();
        // Añade un header alternativo específicamente para las páginas de producto
        //add_action('get_header', 'load_custom_header_for_products');
        // Remueve los hooks del header que podrían estar agregando contenido no deseado
        //remove_all_actions('wp_head');
    }
}

add_action('wp', 'update_header_for_woocommerce_product_pages');

add_filter( 'woocommerce_add_to_cart_validation', 'custom_add_to_cart_validation', 10, 4 );
function custom_add_to_cart_validation( $passed, $product_id, $quantity, $variation_id = 0 ) {
    $product_cart_id = WC()->cart->generate_cart_id( $product_id );
    $in_cart = WC()->cart->find_product_in_cart( $product_cart_id );

    if ( $in_cart ) {
        wc_add_notice( sprintf( __( 'You have bought a unique product to read online and/or download it, so is not necesary to add another one. <a href="%s" class="button wc-forward">View cart</a>', 'text-domain' ), wc_get_cart_url() ), 'error' );
        return false;
    }

    return $passed;
}


/******************************************************************************************************************************************************************
 *
 * NO MOVER LAS SIGUIENTES LINEAS, SON PARA DAR FUNCIONALIDAD A LA API DE AUTENTIFICACIÓNCONECTIVIDAD EN MAILER LITE/
 * FIRMADO: ESTEBAN SALGADO
 * 
 ******************************************************************************************************************************************************************/

function subscribe_user_to_mailerlite($user_id) {
    $user_info = get_userdata($user_id);
    $email = $user_info->user_email;

    $api_key = get_option('mailerlite_api_key');
    $group_id = '138377344715851095';  // Asegúrate de que este ID es correcto

    $url = "https://api.mailerlite.com/api/v2/groups/{$group_id}/subscribers";
    $body = json_encode([
        'email' => $email,
        // 'name' => $user_info->first_name,  // Añadir si quieres pasar el nombre
    ]);

    $args = [
        'body'        => $body,
        'headers'     => [
            'Content-Type' => 'application/json',
            'X-MailerLite-ApiKey' => $api_key
        ],
        'method'      => 'POST',
        'data_format' => 'body'
    ];

    $response = wp_remote_post($url, $args);
    if (is_wp_error($response)) {
        error_log('Error en la suscripción a MailerLite: ' . $response->get_error_message());
    } else {
        // Loguear la respuesta para entender lo que devolvió la API
        error_log('Respuesta de MailerLite: ' . wp_json_encode($response));
    }
}
add_action('user_register', 'subscribe_user_to_mailerlite');

//WP USER MANAGER MANAGE DESACTIVATE MAILS
add_filter('wpum_email_disable_new_user_notification', '__return_true');
add_filter('wpum_email_disable_admin_new_user_notification', '__return_true');



add_action( 'woocommerce_thankyou', 'custom_thank_you_page_redirect', 10, 1 );
function custom_thank_you_page_redirect( $order_id ) {
    if ( !$order_id ) return;

    // Obtener el objeto de la orden
    $order = wc_get_order( $order_id );

    // Verifica si el objeto de la orden está bien y si el estado no es fallido
    if ( $order && ! $order->has_status( 'failed' ) ) {
        // Define aquí la URL a la que deseas redirigir
        $url = home_url( '/thank-you/' ); // Usa una URL relativa que se adapte dinámicamente a cualquier instalación de WordPress

        wp_safe_redirect( $url );
        exit;
    }
}

// CAPTCHA
// Función para generar el CAPTCHA
function validar_captcha_wpum($errors, $values) {
    if (!session_id()) {
        session_start();
    }

    // Asumiendo que el campo del CAPTCHA se llama 'captcha_code' y que los datos del CAPTCHA están en la sesión
    if (isset($_POST['captcha_code'], $_SESSION['captcha_prefix'])) {
        require_once(plugin_dir_path(__FILE__) . 'path/to/really-simple-captcha/really-simple-captcha.php');
        $captcha_instance = new ReallySimpleCaptcha();

        // Verificación del CAPTCHA
        $correct = $captcha_instance->check($_SESSION['captcha_prefix'], $_POST['captcha_code']);

        // Elimina el archivo de imagen del CAPTCHA independientemente de si es correcto o no
        $captcha_instance->remove($_SESSION['captcha_prefix']);

        if (!$correct) {
            $errors->add('invalid_captcha', 'The CAPTCHA code entered was incorrect. Please try again.');
        }

        // Limpia los datos de CAPTCHA de la sesión
        unset($_SESSION['captcha_word'], $_SESSION['captcha_prefix']);
    } else {
        $errors->add('empty_captcha', 'CAPTCHA code is required.');
    }
}

add_action('validate_registration', 'validar_captcha_wpum', 10, 2);



/******************************************************************************************************************************************************************
 *
 * NO MOVER LAS SIGUIENTES LINEAS, SON PARA DAR FUNCIONALIDAD A LA API DE AUTENTIFICACIÓNCONECTIVIDAD EN MAILER LITE/
 * FIRMADO: ESTEBAN SALGADO
 * 
 ******************************************************************************************************************************************************************/
function agregar_boton_compra_credito_en_carrito() {
    echo '<form action="' . esc_url( wc_get_checkout_url() ) . '" method="post">';
    // Este campo es opcional y su implementación depende de si el plugin lo permite.
    echo '<input type="hidden" name="payment_method" value="paypal" />';
    echo '<button type="submit" class="button alt">Comprar con tarjeta de crédito</button>';
    echo '</form>';
}

add_action('woocommerce_after_cart_table', 'agregar_boton_compra_credito_en_carrito');
