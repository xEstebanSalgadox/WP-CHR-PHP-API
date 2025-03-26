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
        wc_add_notice( sprintf( __( 'This product is already in your cart. <a href="%s" class="button wc-forward">View cart</a>', 'text-domain' ), wc_get_cart_url() ), 'error' );
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

// // CAPTCHA
// // Función para validar el CAPTCHA en el registro
// function validar_captcha_wpum($errors, $values) {
//     if (!session_id()) {
//         session_start();
//     }
// 	echo $_SESSION['captcha_prefix'];

//     if (isset($_POST['captcha_code'], $_SESSION['captcha_prefix'])) {
//         require_once(plugin_dir_path(__FILE__) . 'path/to/really-simple-captcha/really-simple-captcha.php');
//         $captcha_instance = new ReallySimpleCaptcha();

//         // Verificación en minúsculas para evitar problemas de mayúsculas/minúsculas
//         $correct = strtolower($captcha_instance->check($_SESSION['captcha_prefix'], $_POST['captcha_code'])) === strtolower($_POST['captcha_code']);

//         // Log para depuración
//         echo 'CAPTCHA Code Submitted: ' . ($_POST['captcha_code'] ?? 'not set');

//         // Elimina los archivos temporales
//         $captcha_instance->remove($_SESSION['captcha_prefix']);

//         if (!$correct) {
//             $errors->add('invalid_captcha', __('The CAPTCHA code entered was incorrect. Please try again.', 'your-text-domain'));
//         }

//         // Limpia la sesión
//         unset($_SESSION['captcha_word'], $_SESSION['captcha_prefix']);
//     } else {
//         $errors->add('empty_captcha', __('CAPTCHA code is required.', 'your-text-domain'));
//     }

//     return $errors;
// }

// add_action('wpum_before_user_registration_validation', 'validar_captcha_wpum', 10, 2);





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
        wp_redirect(home_url('/log-in/?warning-register=true')); // Redirige a la página de registro
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




/*********************************************************************************************************************************************************/


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
        return '<a href="' . home_url('/log-in?warning-register=true') . '" class="button alt">' . __('Buy', 'woocommerce') . '</a>';
    }
    return $button;
}, 10, 2);





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
    echo '<a href="' . esc_url(get_permalink(wc_get_page_id('shop'))) . '" class="button wc-backward">Add other product</a>';
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
