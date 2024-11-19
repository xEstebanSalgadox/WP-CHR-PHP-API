}



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
