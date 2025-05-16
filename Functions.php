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
        'username' => $user->user_,
        'email' => $user->user_email,
    ], 200);
}

// function book_reader_get_books($request) {
//     $email = sanitize_email($request['email']);
//     $user1 = get_user_by('email', $email); // Cambiado de $user1 a $user para mantener consistencia

//     if (!$user1) {
//         return new WP_REST_Response(['error' => 'User not found'], 404);
//     }
	
// 	//var_dump($user1);
// 	//var_dump($request['email']);
	

//     // Obtener pedidos del usuario y registrar la cantidad de pedidos encontrados
//     $customer_orders = wc_get_orders(array(
//         'customer_id' => $user1->ID,
//         'status' => 'completed',
//         'limit' => -1
//     ));

//     $purchased_books = [];

//     // Itera sobre cada pedido para obtener los productos
//     foreach ($customer_orders as $order) {
//         foreach ($order->get_items() as $item) {
//             $product = $item->get_product();

//             if ($product) {
//                 // Obtener categorías del producto
//                 $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
                
//                 // Verificar si el producto está en la categoría "book"
//                 if (in_array('Books', $categories)) {
//                     $purchased_books[] = [
//                         'id' => (int) $product->get_id(),
//                         'name' => $product->get_name()
//                     ];
//                 }
//             }
//         }
//     }    
// 	//var_dump($purchased_books);

//     // Si no se encontraron productos con la etiqueta "book"
//     if (empty($purchased_books)) {
//         return new WP_REST_Response(['error' => 'No books found'], 404);
//     }
	
// 	$purchased_books[] = [
// 		//'id' => strval((int) $product->get_id()), // Convierte a entero y luego a string
//     	'id' => strval(intval($product->get_id())), 
// 		'name' => $product->get_name()
// 	];


// //     return new WP_REST_Response([
// //         'purchased_products' => $purchased_books
// //     ], 200);
// // 	return new WP_REST_Response(
// //     [
// //         'purchased_products' => array_map(function ($book) {
// //             return [
// //                 'id' => intval($book['id']), // Forzamos entero
// //                 'name' => $book['name']
// //             ];
// //         }, $purchased_books)
// //     ], 200);
// 	return [
// 		'purchased_products' => $purchased_books
// 	];
// //     return new WP_REST_Response(
// // 		json_decode(json_encode(['purchased_products' => $purchased_books]), true), 
// // 		200
// // 	);

// }



function book_reader_get_books($request) {
    $email = sanitize_email($request['email']);
    $user = get_user_by('email', $email);

    if (!$user) {
        return new WP_REST_Response(['error' => 'User not found'], 404);
    }

    $customer_orders = wc_get_orders(array(
        'customer_id' => $user->ID,
        'status' => 'completed',
        'limit' => -1
    ));

    $purchased_books = [];

    foreach ($customer_orders as $order) {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            if ($product) {
                $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));

                if (in_array('Books', $categories)) {
                    $book_id = (int) $product->get_id();
                    // Evitar duplicados usando ID como clave
                    $purchased_books[$book_id] = [
                        'id' => $book_id,
                        'name' => $product->get_name()
                    ];
                }
            }
        }
    }

    // Convertir a array plano
    $purchased_books = array_values($purchased_books);

    return new WP_REST_Response([
        'purchased_products' => $purchased_books
    ]);
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
        wc_add_notice( sprintf( __( 'This product is already in your cart. <a href="%s" class="button wc-forward">View cart</a>', 'text-domain' ), wc_get_cart_url() ), 'error' );
        return false;
    }

    return $passed;
}


/******************************************************************************************************************************************************************
 *
 * NO MOVER LAS SIGUIENTES LINEAS, SON PARA DAR FUNCIONALIDAD A LA API DE AUTENTIFICACIÓN CONECTIVIDAD EN MAILER LITE/
 * FIRMADO: ESTEBAN SALGADO
 * 
 ******************************************************************************************************************************************************************/

function subscribe_user_to_mailerlite($user_id) {
    $user_info = get_userdata($user_id);
    if (!$user_info) {
        error_log("No se pudo obtener info del usuario con ID: $user_id");
        return;
    }

    $email = $user_info->user_email;

    $api_key = get_option('mailerlite_api_key');
    $group_id = '138377344715851095'; // ← Asegúrate de que este ID es correcto

    $url = "https://api.mailerlite.com/api/v2/groups/{$group_id}/subscribers";

//     $body = json_encode([
//         'email' => $email,
//         // 'name' => $user_info->first_name ?? '', // Descomenta si es necesario
//     ]);
// 	$body = json_encode([
// 		'email' => $email,
// 		'fields' => [
// 			'name' => $user_info->display_name,
// 			'signup_ip' => $_SERVER['REMOTE_ADDR'],
// 		]
// 	]);

	$body = json_encode([
		'email' => $email,
		'name' => $user_info->display_name,
		'groups' => [$group_id],
		'status' => 'active',
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
// 	echo ''.$response;
// 	stop everything

    if (is_wp_error($response)) {
        error_log('Error en la suscripción a MailerLite: ' . $response->get_error_message());
    } else {
        $response_body = wp_remote_retrieve_body($response);
        error_log('Respuesta de MailerLite: ' . $response_body);
// 		wp_die('<pre>' . print_r($response_body, true) . '</pre>');
    }
}

// Hook correcto para WP User Manager
add_action('wpum_after_user_register', 'subscribe_user_to_mailerlite');
add_action('wpum_before_user_register', 'subscribe_user_to_mailerlite');
add_action('user_register', 'subscribe_user_to_mailerlite');
add_action('profile_update', 'subscribe_user_to_mailerlite');

//WP USER MANAGER MANAGE DESACTIVATE MAILS
add_filter('wpum_email_disable_new_user_notification', '__return_true');
add_filter('wpum_email_disable_admin_new_user_notification', '__return_true');


//NO SÉ PARA QUE ESTO, YA NO PUEDO UTILIZAR ESTO
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



// /******************************************************************************************************************************************************************
//  *
//  * NO MOVER LAS SIGUIENTES LINEAS, SON PARA REGISTRO EN VARIOS GRUPOS DE MAILER LITE
//  * FIRMADO: ESTEBAN SALGADO
//  * 
//  ******************************************************************************************************************************************************************/

// add_action('wpum_a4fter_user_register', function($user_id, $form_data) {
// function subscribe_user_to_mailerlite_checks($user_id, $form_data = []) {
//     $subscriptions = [
// //         'mailerlite_subscribe_game'   => ['group_id' => '154591874021066678', 'interest' => 'Game'],
// //         'mailerlite_subscribe_novel'  => ['group_id' => '154591865809667778', 'interest' => 'Novel'],
// //         'mailerlite_subscribe_leader' => ['group_id' => '154591888077227574', 'interest' => 'Eagle-Leader'],
// //         'mailerlite_subscribe_news'   => ['group_id' => '154591901599664086', 'interest' => 'News'],
//         'mailerlite_subscribe_game'   => ['group_id' => '154591874021066678'],
//         'mailerlite_subscribe_novel'  => ['group_id' => '154591865809667778'],
//         'mailerlite_subscribe_leader' => ['group_id' => '154591888077227574'],
//         'mailerlite_subscribe_news'   => ['group_id' => '154591901599664086'],
//     ];
	

//     $user_info = get_userdata($user_id);
//     $email     = $user_info->user_email;
//     $name      = $user_info->display_name;

//     $api_key = get_option('mailerlite_api_key');

//     foreach ($subscriptions as $field => $data) {
// 		$group_id = $data['group_id'];
// 		$url = "https://api.mailerlite.com/api/v2/groups/{$group_id}/subscribers";

// 		$is_checked = !empty($form_data[$field]) || get_user_meta($user_id, $field, true);


// 		//colocar esta linea aquí imprime dos 1
// 		wp_die('<pre>' .  print_r($is_checked). empty($form_data[$field]). get_user_meta($user_id, $field, true) . '</pre>');

//         if ($is_checked) {
// 			$body = json_encode([
// 				'email' => $email,
// 				'name'  => $name,
// 				'groups' => [$group_id],
// 				'status' => 'active',
// 			]);

// 			$args = [
// 				'body'        => $body,
// 				'headers'     => [
// 					'Content-Type' => 'application/json',
// 					'X-MailerLite-ApiKey' => $api_key
// 				],
// 				'method'      => 'POST',
// 				'data_format' => 'body'
// 			];

// 			$response = wp_remote_post($url, $args);
			
// 			//colocar esta linea aquí y no arriba no imprime nada, da la impresión que no entra al if
// 			wp_die('<pre>' .  print_r($is_checked). empty($form_data[$field]). get_user_meta($user_id, $field, true) . '</pre>');
// 			if (is_wp_error($response)) {
// 				error_log('MailerLite error: ' . $response->get_error_message());
// 				wp_die('<pre>' . print_r($response_body, true) . '</pre>');
// 			} else {
// 				error_log('MailerLite response: ' . wp_remote_retrieve_body($response));
// 				wp_die('<pre>' . print_r($response_body, true) . '</pre>');
// 			}
// 		}
// 	}
// }
// 
// 
// 
add_filter('wpum_get_registration_fields', function($fields) {
    $fields['mailerlite_subscribe_game'] = [
        'label'       => __('', 'your-textdomain'),
        'type'        => 'checkbox',
        'required'    => false,
        'meta'        => true,
        'priority'    => 99,
        'description' => __('The game', 'your-textdomain'),
    ];
    $fields['mailerlite_subscribe_novel'] = [
        'label'       => __('', 'your-textdomain'),
        'type'        => 'checkbox',
        'required'    => false,
        'meta'        => true,
        'priority'    => 99,
        'description' => __('The novel', 'your-textdomain'),
    ];
    $fields['mailerlite_subscribe_leader'] = [
        'label'       => __('', 'your-textdomain'),
        'type'        => 'checkbox',
        'required'    => false,
        'meta'        => true,
        'priority'    => 99,
        'description' => __('Become an eagle-leader', 'your-textdomain'),
    ];
    $fields['mailerlite_subscribe_news'] = [
        'label'       => __('', 'your-textdomain'),
        'type'        => 'checkbox',
        'required'    => false,
        'meta'        => true,
        'priority'    => 99,
        'description' => __('Receive news', 'your-textdomain'),
    ];
    return $fields;
});

add_action('wpum_after_user_register', 'subscribe_user_to_mailerlite_checks', 10, 2);
add_action('wpum_before_user_register', 'subscribe_user_to_mailerlite_checks', 10, 2);
add_action('user_register', function($user_id) {
    subscribe_user_to_mailerlite_checks($user_id, []);
}, 10, 1);
add_action('profile_update', function($user_id) {
    subscribe_user_to_mailerlite_checks($user_id, []);
}, 10, 1);

// add_action('wpum_user_registered', 'subscribe_user_to_mailerlite_checks', 10, 2);


function subscribe_user_to_mailerlite_checks($user_id, $form_data = []) {
    $subscriptions = [
        'mailerlite_subscribe_game'   => ['group_id' => '154591874021066678'],
        'mailerlite_subscribe_novel'  => ['group_id' => '154591865809667778'],
        'mailerlite_subscribe_leader' => ['group_id' => '154591888077227574'],
        'mailerlite_subscribe_news'   => ['group_id' => '154591901599664086'],
    ];

    $user_info = get_userdata($user_id);
    if (!$user_info) return;

    $email = $user_info->user_email;
    $name  = $user_info->display_name;

    $api_key = get_option('mailerlite_api_key');
    if (!$api_key) return;

    foreach ($subscriptions as $field => $data) {
        $group_id = $data['group_id'];
        $url      = "https://api.mailerlite.com/api/v2/groups/{$group_id}/subscribers";

        // Evaluar fuente de datos (form o meta)
//         $from_form = isset($_POST['wpum_register'][$field]) && $_POST['wpum_register'][$field] === '1';
		$from_form = isset($_POST[$field]) && $_POST[$field] === '1';
		$from_meta = get_user_meta($user_id, $field, true);
		$is_checked = $from_form || $from_meta;
		
// 		no será:
// 		$_POST['mailerlite_subscribe_game']...
// 		así aparece en la documentacion

        // Debug claro (NO LO ES)
        //error_log("Field: $field | from_form: " . var_export($from_form, true) . " | from_meta: " . var_export($from_meta, true) . " | is_checked: " . var_export($is_checked, true));

//  		wp_die('<pre>' . var_dump($_POST) . '</pre>'); // este si
        if ($is_checked) {
            $body = json_encode([
                'email'  => $email,
                'name'   => $name,
                'status' => 'active',
            ]);

            $args = [
                'body'    => $body,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-MailerLite-ApiKey' => $api_key,
                ],
                'method'      => 'POST',
                'data_format' => 'body',
            ];

            $response = wp_remote_post($url, $args);
//             $response_body = wp_remote_retrieve_body($response);

            if (is_wp_error($response)) {
                error_log('MailerLite error: ' . $response->get_error_message());
            } else {
                error_log("1Subscribed to {$field} | Response: " . $response_body);
// 				wp_die('<pre>' . print_r("1Not subscribed to {$field}"). " ". var_dump($form_data) . "           " . var_dump($_POST). '</pre>'); // este si
            }
        } else {
            error_log("2Not subscribed to {$field}");
// 			wp_die('<pre>' . print_r("2Not subscribed to {$field}"). " ". var_dump($form_data) . "           " . var_dump($_POST). '</pre>'); // este si
        }
    }
}




// add_action('wpum_after_user_register', function($user_id, $form_data) {
//     $interests = [];

//     if (!empty($form_data['mailerlite_subscribe_game']))   $interests[] = 'Game';
//     if (!empty($form_data['mailerlite_subscribe_novel']))  $interests[] = 'Novel';
//     if (!empty($form_data['mailerlite_subscribe_leader'])) $interests[] = 'Eagle-Leader';
//     if (!empty($form_data['mailerlite_subscribe_news']))   $interests[] = 'News';

	
//     // Si no seleccionó ninguna opción, salimos
//     if (empty($interests)) return;

// 	for $interests hazlo así
//     $user_info = get_userdata($user_id);
//     $email     = $user_info->user_email;
//     $name      = $user_info->display_name;

//     $api_key  = get_option('mailerlite_api_key');
//     $group_id = '138377344715851095'; 1
//     $group_id = '138377344715851095'; 2
//     $group_id = '138377344715851095'; 3
//     $group_id = '138377344715851095'; 4

//     $url = 'https://connect.mailerlite.com/api/subscribers';
//     $body = json_encode([
//         'email' => $email,
//         'name'  => $name,
//         'groups' => [$group_id],
//         'fields' => [
//             'interests' => implode(', ', $interests), // <- si quieres usar campos personalizados
//         ],
//     ]);

//     $args = [
//         'body'    => $body,
//         'headers' => [
//             'Content-Type'  => 'application/json',
//             'Authorization' => 'Bearer ' . $api_key,
//         ],
//         'method'      => 'POST',
//         'data_format' => 'body',
//     ];

//     $response = wp_remote_post($url, $args);

//     if (is_wp_error($response)) {
//         error_log('MailerLite error: ' . $response->get_error_message());
//     } else {
//         error_log('MailerLite response: ' . wp_remote_retrieve_body($response));
//     }
// }, 10, 2);


// add_action('wpum_after_user_register', function($user_id, $form_data) {
//     if (!isset($form_data['mailerlite_subscribe']) || $form_data['mailerlite_subscribe'] !== '1') {
//         return;
//     }

//     $user_info = get_userdata($user_id);
//     $email     = $user_info->user_email;
//     $name      = $user_info->display_name;

//     $api_key  = get_option('mailerlite_api_key');
// //     $group_id = 'AQUÍ_TU_ID_DE_GRUPO';
//     $group_id = '138377344715851095'; // ← Asegúrate de que este ID es correcto

//     $url  = 'https://connect.mailerlite.com/api/subscribers';
//     $body = json_encode([
//         'email' => $email,
//         'name'  => $name,
//         'groups' => [$group_id],
//     ]);

//     $args = [
//         'body'        => $body,
//         'headers'     => [
//             'Content-Type'  => 'application/json',
//             'Authorization' => 'Bearer ' . $api_key,
//         ],
//         'method'      => 'POST',
//         'data_format' => 'body',
//     ];

//     $response = wp_remote_post($url, $args);

//     if (is_wp_error($response)) {
//         error_log('MailerLite error: ' . $response->get_error_message());
//     } else {
//         error_log('MailerLite response: ' . wp_remote_retrieve_body($response));
//     }
// }, 10, 2);




/******************************************************************************************************************************************************************
 *
 * NO MOVER LAS SIGUIENTES LINEAS, SON PARA DAR FUNCIONALIDAD AL CARRITO PARA QUE APAREZCA EL BOTON DE TARJETA DE CREDITO
 * FIRMADO: ESTEBAN SALGADO
 * 
 ******************************************************************************************************************************************************************/

// add_action('woocommerce_before_checkout_form', 'add_paypal_and_card_buttons1');
// add_action('woocommerce_before_checkout', 'add_paypal_and_card_buttons1');
add_action('woocommerce_after_cart_totals', 'add_paypal_and_card_buttons1');
// add_action('woocommerce_after_add_to_cart_button', 'add_paypal_and_card_buttons1');

function add_paypal_and_card_buttons1() {
    global $woocommerce;
    $total = $woocommerce->cart->total;
	
	// Obtener la última orden de WooCommerce del usuario
    $orders = wc_get_orders([
        'limit' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'customer' => get_current_user_id()
    ]);
//     $last_order = !empty($orders) ? $orders[0]->get_id() : 0;
	$last_order = isset($orders[0]) ? $orders[0]->get_id() : 0;
	
    ?>


	<!-- Overlay con el spinner -->
	<div id="loading-overlay" style="display: none;">
		<p>Your payment is being processed</p>
		<div class="spinner"></div>
	</div>

    <div id="paypal-button-container"></div>

	<style>
		/* Estilos del overlay */
		#loading-overlay {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.85);
			display: flex;
			justify-content: center;
			align-items: center;
			z-index: 9999;
			flex-direction: column;
			/* display: none; */
		}
		
		#loading-overlay p{
			color: #fff;
			text-transform: uppercase;
		}
		/* Estilos del spinner */
		.spinner {
			border: 4px solid rgba(255, 255, 255, 0.3);
			border-top: 4px solid #fff;
			border-radius: 50%;
			width: 50px;
			height: 50px;
			animation: spin 1s linear infinite;
		}

		@keyframes spin {
			0% { transform: rotate(0deg); }
			100% { transform: rotate(360deg); }
		}
	</style>

    <script>
		function showLoadingOverlay() {
			document.getElementById("loading-overlay").style.display = "flex";
		}

		function hideLoadingOverlay() {
			document.getElementById("loading-overlay").style.display = "none";
		}
        paypal.Buttons({
            createOrder: function(data, actions) {
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: '<?php echo esc_js($total); ?>' // WooCommerce maneja los productos
                        }
                    }]
                });
            },
            onApprove: function(data, actions) {
                return actions.order.capture().then(function(details) {
            		showLoadingOverlay();
					
                    var transactionId = details.id;
//                     var orderNumber = details.purchase_units[0].payments.captures[0].id; // Corrección
					var orderNumber = details.purchase_units?.[0]?.payments?.captures?.[0]?.id || 'N/A';
                    //var wooOrderId = '<?php //echo $last_order; ?>';

                    // Redirigir con los datos en la URL
                    window.location.href = '/order-processing/?transaction_id=' + transactionId;
                });
            }
        }).render('#paypal-button-container');
    </script>

    <?php
}



/************************************************************************************/
/*																					*/
/* 			SIEMPRE VALIDAR QUE ESTÉN LOGUEADOS ANTES DE AÑADIR AL CARRITO			*/
/*																					*/
/************************************************************************************/

add_filter('woocommerce_add_to_cart_validation', 'redirect_non_logged_users_to_register', 10, 2);

function redirect_non_logged_users_to_register($passed, $product_id) {
    if (!is_user_logged_in()) {
        wp_redirect(home_url('/register/?warning-register=true')); // Redirige a la página de registro
        exit;
    }
    return $passed;
}


/************************************************************************************/
/*																					*/
/*									BOTON CHECKOUT									*/
/*																					*/
/************************************************************************************/

add_action('wp_footer', function () {
    if (!is_checkout()) return; // Solo ejecuta en la página de checkout

    $client_id = get_option('woocommerce-ppcp-settings')['client_id'] ?? '';
    $client_secret = get_option('woocommerce-ppcp-settings')['client_secret'] ?? '';
    $merchant_id = get_option('woocommerce-ppcp-settings')['merchant_id'] ?? '';
// 	add_paypal_and_card_buttons1();
	echo 'HOLA';
	?>
<script nonce="" src="https://www.paypal.com/sdk/js?client-id=BAAC47b-n-xN_YVZVIO-nyMF5vNzuWAucdCi7Cvdb6gqmT9ysevzyg1l2wcZYG67YZb_XTg6eCFuLG0hm4&amp;currency=USD&amp;integration-date=2024-12-02&amp;components=buttons,funding-eligibility,fastlane&amp;vault=false&amp;commit=false&amp;intent=capture&amp;disable-funding=bancontact,blik,eps,ideal,mybank,p24,trustly,multibanco,sepa&amp;enable-funding=venmo,paylater" data-partner-attribution-id="Woo_PPCP" data-client-metadata-id="f2d2a227126d4b5dbc3f5ed09677be94" data-uid="uid_aqjrjxywpthtzfkcttjdxdqgukkehj"></script>
	<?php
    if (current_user_can('administrator')) { // Solo para admins
		
//         echo '<br>';
//         echo '<pre>';
//         var_dump($client_id);
//         echo '<br>';
//         var_dump($client_secret);
//         echo '<br>';
//         var_dump($merchant_id);
//         echo '<br>';
//         var_dump($tokens ?? null); // Asegura que $tokens está definido
//         echo '<br>';
//         var_dump($token ?? null); // Asegura que $token está definido
//         echo '</pre>';
    }
	
	
	
	/*LINEAS DE FUNCIONALIDAD DEBEN SER IDENTICAS A LAS DE ARRIBA*/
	
	//global $woocommerce;
    $total = $woocommerce->cart->total;
	
	// Obtener la última orden de WooCommerce del usuario
    $orders = wc_get_orders([
        'limit' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'customer' => get_current_user_id()
    ]);
    $last_order = !empty($orders) ? $orders[0]->get_id() : 0;
	
    ?>


	<!-- Overlay con el spinner -->
	<div id="loading-overlay" style="display: none;">
		<p>Your payment is being processed</p>
		<div class="spinner"></div>
	</div>

    <div id="paypal-button-container"></div>

	<style>
		/* Estilos del overlay */
		#loading-overlay {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.85);
			display: flex;
			justify-content: center;
			align-items: center;
			z-index: 9999;
			flex-direction: column;
			/* display: none; */
		}
		
		#loading-overlay p{
			color: #fff;
			text-transform: uppercase;
		}
		/* Estilos del spinner */
		.spinner {
			border: 4px solid rgba(255, 255, 255, 0.3);
			border-top: 4px solid #fff;
			border-radius: 50%;
			width: 50px;
			height: 50px;
			animation: spin 1s linear infinite;
		}

		@keyframes spin {
			0% { transform: rotate(0deg); }
			100% { transform: rotate(360deg); }
		}
	</style>

    <script>
		function showLoadingOverlay() {
			document.getElementById("loading-overlay").style.display = "flex";
		}

		function hideLoadingOverlay() {
			document.getElementById("loading-overlay").style.display = "none";
		}
		
        paypal.Buttons({
            createOrder: function(data, actions) {
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: '<?php echo esc_js($total); ?>' // WooCommerce maneja los productos
                        }
                    }]
                });
            },
            onApprove: function(data, actions) {
                return actions.order.capture().then(function(details) {
            		showLoadingOverlay();
					
                    var transactionId = details.id;
                    var orderNumber = details.purchase_units[0].payments.captures[0].id; // Corrección
                    //var wooOrderId = '<?php //echo $last_order; ?>';

                    // Redirigir con los datos en la URL
                    window.location.href = '/order-processing/?transaction_id=' + transactionId;
                });
            }
        }).render('#paypal-button-container');
    </script>

    <?php	
});




/************************************************************************************/

/*									PRODUCT SHOP									*/

/************************************************************************************/


// function add_languages_to_products() {
//     global $product;
//     $languages = get_post_meta( $product->get_id(), 'languages', true );
//     if ( ! empty($languages) ) {
//         echo '<p class="product-languages">Idiomas: ' . esc_html($languages) . '</p>';
//     }
// }

add_action( 'woocommerce_shop_loop_item_title', 'add_languages_to_products', 23 );
function add_languages_to_products() {
    global $product;
    $languages = get_post_meta( $product->get_id(), 'languages', true );
    if ( ! empty($languages) ) {
        $languages_array = explode(',', $languages);
		echo '<div class="product-flags" style="margin-bottom: 0px">';
        foreach ($languages_array as $language) {
            $image_url = "https://championsrenaissance.com/wp-content/uploads/2025/01/". $language. ".png";
            echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($language) . '"/>';
        }
		echo '</div>';
    }
	echo '<div style="height: 18px;"></div>';
}

// add_action( 'woocommerce_shop_loop_item_title', 'add_moreinfo_to_products', 1 );
// function add_moreinfo_to_products() {
//     global $product;
//     $more_info_url = get_post_meta( $product->get_id(), 'more_info_url', true );
//     if ( ! empty($more_info_url) ) {
//         echo '<a href="' . esc_url($more_info_url) . '" class="button" style="margin: 40px 0; text-align: center;">More Info</a>';
//     }
// }

add_action( 'woocommerce_after_shop_loop_item', 'add_custom_links_to_products', 1 );
function add_custom_links_to_products() {
    global $product;
    $custom_links = get_post_meta( $product->get_id(), 'custom_links', true );

    if ( ! empty($custom_links) ) {
        // Divide los enlaces por líneas
        $links = explode("\n", $custom_links);

        // Itera sobre cada enlace
        foreach ( $links as $link ) {
            // Divide la etiqueta y la URL por el carácter '|'
            $parts = explode('|', $link);

            if ( count($parts) === 2 ) {
                $label = trim($parts[0]);
                $url = trim($parts[1]);

                // Si la URL es válida, muestra el enlace
                if ( filter_var($url, FILTER_VALIDATE_URL) ) {
                    echo '<a href="' . esc_url($url) . '" class="button" style="margin: 10px 0; text-align: center;">' . esc_html($label) . '</a>';
                }
            }
        }
    }
}

//REGISTER BEFORE ADD TO CART
add_filter('woocommerce_product_add_to_cart_text', function($text) {
    if (!is_user_logged_in()) {
        return __('Buy', 'woocommerce');
    }
    return $text;
});

add_filter('woocommerce_loop_add_to_cart_link', function($button, $product) {
    if (!is_user_logged_in()) {
        $login_url = wp_login_url(get_permalink());
        return '<a href="' . home_url('/register?warning-register=true') . '" class="button alt">' . __('Buy', 'woocommerce') . '</a>';
    }
    return $button;
}, 10, 2);




/************************************************************************************************************************/

/*													PRODUCTO RECOMENDADO												*/

/************************************************************************************************************************/


add_action('woocommerce_product_query', function($q) {
    if (!is_page('product-shop')) return;

    $meta_query = $q->get('meta_query') ?: [];
	
	$meta_query[] = [
        'relation' => 'OR', // Usamos 'OR' para combinar las dos condiciones
        [
            'key'     => 'is_featured_product',
            'value'   => 'yes',
            'compare' => 'NOT EXISTS',
        ],
        [
            'key'     => 'is_featured_product',
            'value'   => 'yes',
            'compare' => 'EXISTS',
        ]
    ];

    $q->set('orderby', 'meta_value');
    $q->set('order', 'DESC');
    $q->set('meta_query', $meta_query);
});

//OPTION 1
add_action( 'woocommerce_before_shop_loop_item_title', 'add_featured_class_to_product', 25 );
function add_featured_class_to_product() {
    global $product;
    $featured_product = get_post_meta( $product->get_id(), 'is_featured_product', true );
    
    if ( ! empty( $featured_product ) && $featured_product === 'yes' ) {
        echo '<div class="featured-product" style="display: block; width: 100%; display: flex !important; justify-content: flex-start; flex-wrap: nowrap; flex-direction: row; align-items: center; padding: 0px 30px; margin-top: -75px; left: 20px; position: absolute;"><!--<img decoding="async" src="https://championsrenaissance.com/wp-content/uploads/2025/04/discover_books_answers.png" alt="ru">--></div>';
    }
}

//OPTION 2
// add_action( 'woocommerce_shop_loop_item_title', 'add_featured_class_to_product', 25 );
// function add_featured_class_to_product() {
//     global $product;
//     $featured_product = get_post_meta( $product->get_id(), 'is_featured_product', true );
//     if ( ! empty( $featured_product ) && $featured_product === 'yes' ) {
//         echo '<div class="featured-product" style="display: block; width: 50px;"><img decoding="async" src="https://championsrenaissance.com/wp-content/uploads/2025/04/discover_books_answers.png" alt="ru"></div>';
//     }
// }


/***********************************************************************************************************************/

/*											BOTON INCIO LIBRO ONLINE

/***********************************************************************************************************************/
add_action('wp_footer', 'mostrar_html_flotante_en_urls_personalizadas');

function mostrar_html_flotante_en_urls_personalizadas() {
    if (is_singular()) {
        $url_actual = $_SERVER['REQUEST_URI'];

        // Verifica si la URL contiene el patrón deseado ENG y ESP
        if (preg_match('#/(one-exceptional-mind-act|una-mente-excepcional-acto)-[a-z0-9-]+/#', $url_actual) ||
			preg_match('#/(una-mente-excepcional-sinopsis|una-mente-excepcional-introduccion|una-mente-excepcional-prefacio|una-mente-excepcional-epilogo)/#', $url_actual) ||
			preg_match('#/(one-exceptional-mind-synopsis|one-exceptional-mind-introduction|one-exceptional-mind-preface|one-exceptional-mind-epilogue)/#', $url_actual)
		   ) {
            ?>
            <div style="
                position: fixed;
                bottom: 5px;
                right: 20px;
                z-index: 9999;
				width: 15%;
            ">
				<button id="index_btn">
					Index
				</button>
				<button id="exit_btn" onclick="window.location.href='https://championsrenaissance.com/product-shop/?wlfilter=1&woolentor_product_cat=books';">
					Exit
				</button>
				
				 <nav class="main-menu">
					 <button onclick="closePopupIndex()" style="color: black; font-size: 12px; border: 1px solid black; padding: 7px; border-radius:30px; display: flex; margin: auto; margin-right: 0;"><img src="https://championsrenaissance.com/wp-content/uploads/2025/05/7560626.png" style="width: 22px;"></button>
					<div class="settings"></div>
					<?php if (preg_match('#/(una-mente-excepcional-acto)-[a-z0-9-]+/#', $url_actual) || preg_match('#/(una-mente-excepcional-sinopsis|una-mente-excepcional-introduccion|una-mente-excepcional-prefacio|una-mente-excepcional-epilogo)/#', $url_actual)) { ?>
					 <h3 style="text-align: center; text-transform: uppercase; font-family: 'Times New Roman'; font-weight: bold;">Índice</h3>
					 <?php echo do_shortcode('[wpcode id="28045"]'); ?>
					<?php } ?>
					
					<?php if (preg_match('#/(one-exceptional-mind-act)-[a-z0-9-]+/#', $url_actual) || preg_match('#/(one-exceptional-mind-synopsis|one-exceptional-mind-introduction|one-exceptional-mind-preface|one-exceptional-mind-epilogue)/#', $url_actual)) { ?>
					 <h3 style="text-align: center; text-transform: uppercase; font-family: 'Times New Roman'; font-weight: bold;">Index</h3>
					 <?php echo do_shortcode('[wpcode id="27911"]'); ?>
					<?php } ?>
				 </nav>
			</div>
			<script>
				function closePopupIndex() {
					const index_popup = document.querySelector(".main-menu");
					if (index_popup.style.display === "none" || index_popup.style.display === "") {
						index_popup.style.display = "block";
					} else {
						index_popup.style.display = "none";
					}
				}
			  document.addEventListener("DOMContentLoaded", function () {
				const index_btn = document.querySelector("#index_btn");
				const index_popup = document.querySelector(".main-menu");

				if (!index_btn || !index_popup) return;

				index_btn.addEventListener("click", function () {
				  if (index_popup.style.display === "none" || index_popup.style.display === "") {
					index_popup.style.display = "block";
				  } else {
					index_popup.style.display = "none";
				  }
				});
			  });
			</script>

			<style>
				.main-menu, nav.main-menu.expanded {
					display: none;
					top:0;
					bottom:0;
					left: 0;
					right:0;
					width: 45%;
					height: 85%;
					margin: auto;
					position: fixed;
					overflow:hidden;
					overflow-y: auto;   
					background:#efefef;
					border-radius: 30px;
					border: 1px solid #cfcfcf;
					transition:width .2s linear;
					-webkit-transition:width .2s linear;
					-webkit-transform:translateZ(0) scale(1,1);
					box-shadow: 0 0 20px rgba(0, 0, 0, 0.25);
					opacity:1;
				}

				@media only screen and (max-width: 768px) {
					.main-menu {
						width:83%;
						height: 80%;
						box-shadow: 0 0 20px rgba(0, 0, 0, 0.25);
					}
				}

				/* STYLES FROM CHRE*/
				nav{
				  padding: 30px;
				  overflow: hidden;
				}

				.toggle-list-1 {
				  display: none;
				}
				.toggle-list-2 {
				  display: none;
				}
				.toggle-list-3 {
				  display: none;
				}

				/* *************** */
				.elementor-button-wrapper {
				  margin: auto;
				}

				button.toggle-button{
				  padding: 12px 24px !important;
				}

				a.elementor-button.elementor-button-index, button.toggle-button {
				  background: #FAC334C9;
				  display:block;
				  color: black;
				  box-shadow: 0px 0px 7px 0px rgba(0, 0, 0, 0.25);
				  font-family: "Times New Roman", Sans-serif;
				  font-size: 22px; 
				  background: #FAC334C9;
				  width: 90%;
				  margin: auto;
				  margin-top: 20px;
				  border-radius: 15px;
				  border-width: 0;
				/* padding: 12px 24px !important; */
				}
				a.elementor-button:hover, .toggle-button:hover {
				  background: #FAC334C9;
				  color:black;
				  cursor: hand;
				}
				#index_btn, #exit_btn{
					color: black;
					font-family: 'Arial';

					font-weight: 600;
					font-size: 18px;
					background: #fff;
					padding: 5px 20px;
					z-index: 1;
					position: relative;
					bottom: -10px;
					border: none;
					box-shadow: 0px 0px 9px rgba(0, 0, 0, 0.3);
					-webkit-box-shadow: 0px 0px 9px rgba(0, 0, 0, 0.3);
					-moz-box-shadow: 0px 0px 9px rgba(0, 0, 0, 0.3);
					
					background: #3A1953;
					color: #fff;
					background: #fff;
					color: #000;
					
					width: 80px;
				}
				#exit_btn {
					background: #fff;
					color: #000;
				}
				@media only screen and (max-width: 768px) {
/* 					#index_btn {
						right: 110px;
						bottom: -5px;
						position: fixed;
					}
					#exit_btn {
						right: 32px;
					} */
					#index_btn {
						right:26px;
					}
					#exit_btn {
						right: 110px;
						bottom: -5px;
						position: fixed;
					}
				}
			</style>
            <?php
        }
        if (preg_match('#/(book-one-exceptional-mind/en/index|book-one-exceptional-mind/es/index)/#', $url_actual)) {
            ?>
            <div id="home_btn_container" style="
            ">
				<a id="home_btn" href="https://championsrenaissance.com/product-shop/?wlfilter=1&woolentor_product_cat=books" style = "">
					Exit
				</a>
			</div>
			<style>
				#home_btn{
					color: black;
					font-family: 'Arial';
					
					color: black;
					font-weight: 800;
					font-size:18px;
					background: #fff;
					padding: 9px 20px !important;

					font-weight: 600;
					font-size:18px;
					background: #fff;
					padding: 5px 20px;
					z-index: 1;
					bottom: -10px;
					border: none;
					box-shadow: 0px 0px 9px rgba(0, 0, 0, 0.3);
					-webkit-box-shadow: 0px 0px 9px rgba(0, 0, 0, 0.3);
					-moz-box-shadow: 0px 0px 9px rgba(0, 0, 0, 0.3);
					
					background: #3A1953;
					color: #fff;
					
					background: #fff;
					color: #000;
				}
				#home_btn_container {
					position: fixed;
					bottom: 0;
					z-index: 9999;
					width: 15%;
					right: 58px;
				}
				@media only screen and (max-width: 768px) {
					#home_btn_container {
						right: 35px !important;
					}
				}
			</style>
            <?php
		}
    }
}


/***********************************************************************************************************************/

/*												PERSONALIZAR REGISTRO

/***********************************************************************************************************************/
// add_filter('the_content', 'personalizar_pagina_registro');

// function personalizar_pagina_registro($content) {
// 	if (!is_page('register')) return $content;

// 	// Reemplazo de texto
// 	$search = 'Already have an account?';
// 	$replacement = 'Login if you have an account <br><br>';
// 	$content = str_replace($search, $replacement, $content);
	
// 	// Reemplazo del checkbox: oculto pero marcado (checked)
// 	$search = 'type="checkbox"';
// 	$replacement = 'type="checkbox" style="display: none;" checked ';
// 	$content = str_replace($search, $replacement, $content);
	
// 	// Reemplazo del checkbox: oculto pero marcado (checked)
// 	$search = 'I have read and accept the';
// 	$replacement = 'To register means that I have read and accept the';
// 	$content = str_replace($search, $replacement, $content);
	
// 	echo"
// 	<script>
// 	document.addEventListener('DOMContentLoaded', function () {
// 		var passwordInput = document.getElementById('user_password');

// 		// Crear el texto de advertencia
// 		var warningText = document.createElement('div');
// 		warningText.textContent = '(8 characters, one capital letter, number and special symbol)';
// 		warningText.style.color = '#c83e3e';
// 		warningText.style.fontSize = '14px';
// 		warningText.style.marginTop = '5px';

// 		// Insertar el texto después del input
// 		passwordInput.insertAdjacentElement('afterend', warningText);
// 	});
// 	</script>
// 	";
// 	return $content;
// }

add_filter('the_content', 'personalizar_pagina_registro');

function personalizar_pagina_registro($content) {
	if (!is_page('register')) return $content;

	libxml_use_internal_errors(true);

	$dom = new DOMDocument();
	$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
	$xpath = new DOMXPath($dom);

	// Ocultar y marcar solo el checkbox de PRIVACIDAD
	$privacyInput = $xpath->query('//input[@type="checkbox" and @id="privacy"]')->item(0);
	if ($privacyInput) {
		$privacyInput->setAttribute('style', 'display:none;');
		$privacyInput->setAttribute('checked', 'checked');
	}

// 	// Cambiar el texto del label del checkbox de privacidad
// 	$privacyLabel = $xpath->query('//label[@for="privacy"]')->item(0);
// 	if ($privacyLabel) {
// 		$privacyLabel->firstChild->nodeValue = 'To register means that I have read and accept the ';
// 	}
	// Obtener HTML procesado
	$body = $dom->getElementsByTagName('body')->item(0);
	$newContent = '';
	foreach ($body->childNodes as $child) {
		$newContent .= $dom->saveHTML($child);
	}
	
	$search = 'I have read and accept the';
	$replacement = 'To register means that I have read and accept the';
	$newContent = str_replace($search, $replacement, $newContent);
	
	
	$search = '<fieldset class="fieldset-mailerlite_subscribe_game';
	$replacement = '<h2 style="text-decoration: underline; text-transform: uppercase;">Select Your Interests</h2><fieldset class="fieldset-mailerlite_subscribe_game';
	$newContent = str_replace($search, $replacement, $newContent);
	

	// Agregar advertencia en campo de contraseña
	$newContent .= "
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var passwordInput = document.getElementById('user_password');
		if (passwordInput) {
			var warningText = document.createElement('div');
			warningText.textContent = '(8 characters, one capital letter, number and special symbol)';
			warningText.style.color = '#c83e3e';
			warningText.style.fontSize = '14px';
			warningText.style.marginTop = '5px';
			passwordInput.insertAdjacentElement('afterend', warningText);
		}
	});
	</script>
	";

	return $newContent;
}



/***********************************************************************************************************************/

/*													BORRAR LAZY LOAD

/***********************************************************************************************************************/

function disable_lazy_load_for_specific_images($content) {
    $content = str_replace('loading="lazy"', '', $content);
    $content = str_replace('lazy', '', $content);
	
    return $content;
}
add_filter('the_content', 'disable_lazy_load_for_specific_images');



/***********************************************************************************************************************/
/*										Botón para ir a product shop en el carrito
/***********************************************************************************************************************/
add_action('woocommerce_before_cart_totals', function() {
    echo '<a href="https://championsrenaissance.com/product-shop/?wlfilter=1&woolentor_product_cat=books" class="button wc-backward" style="display: flex; float: right; margin-bottom: 20px; right: 0; flex-wrap: nowrap; width: max-content; justify-content: flex-end; flex-direction: row;">Add other product</a>';
});

/***********************************************************************************************************************/
/*													ADD EYE PASSWORD
/***********************************************************************************************************************/

function add_password_toggle_script() {
    ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll('input[type="password"]').forEach(function(input) {
                let wrapper = document.createElement("div");
                wrapper.style.position = "relative";
                input.parentNode.insertBefore(wrapper, input);
                wrapper.appendChild(input);

                let toggleButton = document.createElement("button");
                toggleButton.type = "button";
                toggleButton.style.position = "absolute";
                toggleButton.style.right = "10px";
                toggleButton.style.top = "50%";
                toggleButton.style.transform = "translateY(-50%)";
                toggleButton.style.background = "transparent";
                toggleButton.style.border = "none";
                toggleButton.style.cursor = "pointer";
                toggleButton.style.fontSize = "18px";
                toggleButton.style.height = "35px";
                toggleButton.style.paddingRight = "5px";
				toggleButton.classList.add("password-toggle");
				//toggleButton.id = "password-toggle"; // Asignar ID correctamente
				
                let eyeIcon = document.createElement("img");
                eyeIcon.src = "https://championsrenaissance.com/wp-content/uploads/2025/03/eye.png";
                eyeIcon.alt = "Mostrar contraseña";
                eyeIcon.style.width = "20px";

                toggleButton.appendChild(eyeIcon);
                wrapper.appendChild(toggleButton);

                toggleButton.addEventListener("click", function() {
                    if (input.type === "password") {
                        input.type = "text";
                        eyeIcon.src = "https://championsrenaissance.com/wp-content/uploads/2025/03/eye_slash.png";
                        eyeIcon.alt = "Ocultar contraseña";
                    } else {
                        input.type = "password";
                        eyeIcon.src = "https://championsrenaissance.com/wp-content/uploads/2025/03/eye.png";
                        eyeIcon.alt = "Mostrar contraseña";
                    }
                });

                wrapper.appendChild(toggleButton);
            });
			var isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
			var isEdge = /Edg/i.test(navigator.userAgent);

			if (isSafari || isEdge) {
				document.querySelectorAll(".password-toggle").forEach(function (btn) {
					btn.style.display = "none";
				});
			}
        });
    </script>
    <style>
		@supports (-webkit-appearance: none) and (not (caret-color: auto)) {
			.password-toggle {
				display: none !important;
			}
		}
		@supports (-webkit-hyphens: none) and (not (-moz-appearance: none)) {
			.password-toggle {
				display: none !important;
			}
		}
		/* Ocultar en Safari */
		@supports (-webkit-touch-callout: none) and (not (translate: none)) {
			.password-toggle {
				display: none !important;
			}
		}

		/* Ocultar en Edge */
		@supports (-ms-ime-align: auto) {
			.password-toggle {
				display: none !important;
			}
		}
    </style>
    <?php
}
add_action('wp_footer', 'add_password_toggle_script');


/*********************************************************************************************************************************************/
/*                                                        reCaptcha V2 para WP User Manager                                                  */
/*********************************************************************************************************************************************/


// Claves de tu reCAPTCHA v2
define('RECAPTCHA_SITE_KEY', '6Ld-dAwrAAAAAOg0N7fIfPC0hlHDNNjv787y98dW');
define('RECAPTCHA_SECRET_KEY', '6Ld-dAwrAAAAAJD3wTLTAUDd-7umKcYmfar81y7f');

// Mostrar reCAPTCHA en formularios de login y registro
add_action('wpum_before_submit_button_registration_form', 'wpum_add_recaptcha_field', 99);
add_action('wpum_before_submit_button_login_form', 'wpum_add_recaptcha_field', 99);
function wpum_add_recaptcha_field() {
    echo '<div class="recaptcha-wrapper" style="margin: 20px 0;">
        <div class="g-recaptcha" data-sitekey="' . esc_attr(RECAPTCHA_SITE_KEY) . '" style="display: flex; justify-content: center;"></div>
    </div>';
}

add_action( 'wp', function () {
	if ( class_exists( 'WPUM_Form_Login' ) ) {
		$form = WPUM_Form_Login::instance();

		if ( $form->get_step() === 'done' && $form->has_errors() ) {
			$form->step = 0;
		}
	}
} );


add_action('wpum_before_login', function($username) {
    if (empty($_POST['g-recaptcha-response'])) {
		$form = WPUM_Form_Login::instance();
		$form->step = 0; // Forzar que vuelva al paso submit
		throw new Exception(__('Please complete the Captcha.', 'wpum'));
    }

    $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
        'body' => [
            'secret'   => RECAPTCHA_SECRET_KEY,
            'response' => sanitize_text_field($_POST['g-recaptcha-response']),
            'remoteip' => $_SERVER['REMOTE_ADDR'],
        ]
    ]);

    if (is_wp_error($response)) {
		$form = WPUM_Form_Login::instance();
		$form->step = 0; // Forzar que vuelva al paso submit
		throw new Exception(__('Unable to verify Captcha.', 'wpum'));
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($body['success'])) {
		$form = WPUM_Form_Login::instance();
		$form->step = 0; // Forzar que vuelva al paso submit
		throw new Exception(__('Captcha verification failed. Please try again.', 'wpum'));
    }
});

add_action('wpum_before_registration_start', function($userdata) {
    if (empty($_POST['g-recaptcha-response'])) {
        throw new Exception(__('Please complete the Captcha.', 'wpum'));
    }

    $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
        'body' => [
            'secret'   => RECAPTCHA_SECRET_KEY,
            'response' => sanitize_text_field($_POST['g-recaptcha-response']),
            'remoteip' => $_SERVER['REMOTE_ADDR'],
        ]
    ]);

    if (is_wp_error($response)) {
        throw new Exception(__('Unable to verify Captcha.', 'wpum'));
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($body['success'])) {
        throw new Exception(__('Captcha verification failed. Please try again.', 'wpum'));
    }
});

/*********************************************************************************************************************************************/
/*														REMPLAZAR SIGN IN Y SIGN UP
/*********************************************************************************************************************************************/

function replace_sign_in_sign_up_text($content) {
    // Cambia estos slugs a los de tus páginas
    if (is_page(['log-in', 'register'])) {
        // Reemplaza "Sign In" por ""
        $content = str_replace('Sign In', 'Login', $content);

        // Reemplaza "Sign Up" por "Register"
        $content = str_replace('Sign Up', 'Register', $content);
		
        $content = str_replace('Signin', 'Login', $content);
        $content = str_replace('Signup Now', 'Register', $content);
    }

    return $content;
}

add_filter('the_content', 'replace_sign_in_sign_up_text');



/*********************************************************************************************************************************************/
/*														VER TODOS LOS HOOKS DE LA PAGINA
/*********************************************************************************************************************************************/

// add_action('all', function($hook) {
//     if (
//         strpos($hook, 'wpum') !== false || // cualquier hook de WP User Manager
//         strpos($hook, 'elementor') !== false || // algo que Elementor pueda usar
//         strpos($hook, 'form') !== false // cualquier cosa de formulario
//     ) {
//         echo "<div style='font-size:10px;color:red;'>Hook activo: {$hook}</div>";
//     }
// });


// wpum_before_submit_button_registration_form


add_filter('the_content', 'replace_sign_in_sign_up_text');
