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
    $user = get_user_by('email', $email);

    if (!$user) {
        return new WP_REST_Response(['error' => 'User not found'], 404);
    }

    // Obtener pedidos del usuario y registrar la cantidad de pedidos encontrados
    $customer_orders = wc_get_orders(array(
        'customer_id' => $user->ID,
        'status' => 'completed',
        'limit' => -1
    ));
    
    $purchased_books = [];

    // Itera sobre cada pedido para obtener los productos
    foreach ($customer_orders as $order) {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            if ($product) {
                // Obtener etiquetas del producto
                $tags = wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names'));
                
                // Verificar si el producto tiene la etiqueta "book"
                if (in_array('book', $tags)) {
                    $purchased_books[] = $product->get_name();
                }
            }
        }
    }

    // Si no se encontraron productos con la etiqueta "book"
    if (empty($purchased_books)) {
        return new WP_REST_Response(['error' => 'No books found'], 404);
    }

    return new WP_REST_Response([
        'purchased_products' => $purchased_books
    ], 200);
}
