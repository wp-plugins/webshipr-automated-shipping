<?php
/*

Plugin Name: Webshipr for WooCommerce
Plugin URI: http://www.webshipr.com
Description: Automated shipping for WooCommerce
Author: webshipr.com
Author URI: http://www.webshipr.com
Version: 2.1.6

*/

/**
 * Check if WooCommerce is active
 */

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ){
    
    # Which environemnt to connect to
    define("API_RESOURCE", 'https://portal.webshipr.com');

    # Load webshipr library
    require_once('webshipr.php');

    # Dont clash
    if ( ! class_exists( 'WebshiprWC' ) ) {

        class WebshiprWC {

           protected $option_name = 'webshipr_options';
           protected $data = array(
             'api_key' => '',
             'auto_process' => false, 
             'show_search_address' => true, 
             'show_address_bar' => true
           );
        
           private $options;  


           // Constructor to be initialized
           public function __construct() {
                global $woocommerce;                

                // Hook actions
                add_action('woocommerce_admin_order_data_after_order_details', array($this,'show_on_order'));


                /* 
                    As per woocommerce 2.3 reveiw-order.php and payment section was seperated. 
                    Therefore hook has changed, and PUP content moved 
                */

                //if ( version_compare( $woocommerce->version, '2.2', '<' ) ) {
                    add_action('woocommerce_review_order_before_order_total', array($this,'append_dynamic'));
                //}else{
                 //   add_action('woocommerce_review_order_before_payment', array($this,'append_dynamic'));
                //}

                add_action('admin_init', array($this, 'admin_init'));
                add_action('admin_menu', array($this, 'add_page'));


                // Register and load JS and CSS
                add_action('wp_loaded', array($this, 'register_frontend')); 
                add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend'));
                add_action('admin_enqueue_scripts', array($this, 'enqueue_backend'));


                #add_action('woocommerce_review_order_after_shipping', array($this,'append_dynamic'));
                add_action('woocommerce_checkout_order_processed', array($this, 'order_placed'));
                add_action('woocommerce_checkout_update_order_meta', array($this, 'override_delivery'));
                add_action('woocommerce_checkout_process', array($this, 'validate_on_process')); 

                // Autoprocess
                add_action('woocommerce_payment_complete', array($this, 'auto_process'));

                // Hook ajax methods
                add_action("wp_ajax_nopriv_check_rates", array($this, "check_rates"));
                add_action( 'wp_ajax_check_rates', array($this, 'check_rates') );
                
                add_action('woocommerce_after_checkout_shipping_form', array($this, 'set_ajaxurl')); 

                add_action("wp_ajax_nopriv_get_shops", array($this, "ajax_get_shops"));
                add_action( 'wp_ajax_get_shops', array($this, 'ajax_get_shops') );
                


                register_activation_hook(__FILE__, array($this,'activate'));

                // Localization 
                load_plugin_textdomain('WebshiprWC', false, basename( dirname( __FILE__ ) ) . '/languages' );

                // Initialize settings
                $this->options = get_option('webshipr_options');

                // Need to add extra field for locations
                // Display Field
                add_action( 'woocommerce_product_options_general_product_data', array($this, 'woo_add_custom_general_fields') );
                // Save Field
                add_action( 'woocommerce_process_product_meta', array($this, 'woo_add_custom_general_fields_save' ));


           }


           // Register JS scripts
           function register_frontend(){

                // Register CSS
                wp_register_style("ws_css", plugins_url("css/wspup.css", __FILE__));
                
                // Register JS
                wp_register_script("ws_maps", "https://maps.googleapis.com/maps/api/js?sensor=false"); 
                wp_register_script("ws_maplabel", plugins_url("js/maplabel.js", __FILE__));
                wp_register_script("ws_pup", plugins_url("js/wspup.js", __FILE__));
                wp_register_script("ws_js", plugins_url("js/webshipr.js", __FILE__));


           }

           // Enqueue scripts
           function enqueue_frontend(){

                // CSS
                wp_enqueue_style('ws_css');

                // JS
                wp_enqueue_script("jquery"); 
                wp_enqueue_script('ws_maps');
                wp_enqueue_script('ws_maplabel');
                wp_enqueue_script('ws_pup');
                wp_enqueue_script('ws_js');

                // Hook ajax stuff
                wp_localize_script( 'check_rates', 'wsAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ))); 
                wp_localize_script( 'get_shops', 'wsAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ))); 

           }

           // Enqueue scripts backend
           function enqueue_backend(){

                // CSS
                wp_enqueue_style('ws_css');

                // JS
                wp_enqueue_script("jquery"); 
                wp_enqueue_script('ws_js');

                // Hook ajax stuff
                wp_localize_script( 'check_rates', 'wsAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ))); 
                wp_localize_script( 'get_shops', 'wsAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ))); 

           }           


            // Update product location 
           function woo_add_custom_general_fields_save( $post_id ){
                $woocommerce_text_field = $_POST['_webshipr_location'];
                if( !empty( $woocommerce_text_field ) )
                        update_post_meta( $post_id, '_webshipr_location', esc_attr( $woocommerce_text_field ) );

            }

            // Add field under product for stock location
            function woo_add_custom_general_fields() {

                      global $woocommerce, $post;

                      echo '<div class="options_group">';

                      woocommerce_wp_text_input(
                            array(
                                    'id'          => '_webshipr_location',
                                    'label'       => __( 'Webshipr stock location', 'woocommerce' ),
                                    'placeholder' => '',
                                    'desc_tip'    => 'true',
                                    'description' => __( 'This is the stock location that will get transfered to webshipr', 'woocommerce' )
                            )
                    );

                      echo '</div>';

            }

           // Autoprocess
           public function auto_process($order_id){

                // Autoprocess logic
                if((int)$this->options['auto_process'] == 1 && (int)$order_id > 0){ 
                    $woo_order = new WC_Order($order_id);
                    $woo_method_array = reset($woo_order->get_shipping_methods());
                    $ws_rate_id = (preg_match("/WS/", $woo_method_array["method_id"]) ? str_replace("WS", "", $woo_method_array["method_id"]) : -1);
                   
                    // Place order
                    $this->WooOrderToWebshipr($woo_order, $ws_rate_id);
                }
           
           }

           // Get shops AJAX
           public function ajax_get_shops(){
                // Webshipr API Instance
                $api = $this->ws_api($this->options['api_key']); 

                // Check the method
                switch($_POST["method"]){
                    case 'getByZipCarrier':
                        wp_send_json_success($api->getShopsByCarrierAndZip($_POST["zip"], $_POST['carrier']));
                        break;
                    case 'getByZipRate':
                        wp_send_json_success($api->getShopsByRateAndZip($_POST["zip"], $_POST['rate_id']));
                        break;
                    case 'getByAddressRate':
                        wp_send_json_success($api->getShopsByRateAndAddress($_POST['address'], $_POST['zip'], 'DK', $_POST['rate_id']));
                        break;
                    default: 
                        wp_send_json_success(array("error" => 'Method not defined'));
                        break;
                }
           }

           // Validate if pakkeshop is required
           public function validate_on_process(){
                global $woocommerce; 
                $rate_id = ""; 
                if(is_array($_REQUEST["shipping_method"])){
                    $rate_id = $_REQUEST["shipping_method"][0]; 
                }else{
                    $rate_id = $_REQUEST["shipping_method"];
                }

                
                $api = $this->ws_api($this->options['api_key']);
                $rates = $api->GetShippingRates(); 
                $is_dyn_required = false; 

                // Loop through rates, and check if the rate is dyn
                if(is_array($rates)){
                    foreach($rates as $rate){
                        if($rate->dynamic_pickup && (("WS".$rate->id) == $rate_id)){
                            $is_dyn_required = true; 
                        }
                    }  
                }
                
               if($is_dyn_required && strlen($_REQUEST["wspup_id"]) == 0){
                    wc_add_notice( __('Select a pickup point to proceed' , 'WebshiprWC'), "error"); 
               }

           }

           // Set ajax url in checkout
           public function set_ajaxurl(){

            ?>
                <script type="text/javascript">
                        wspup.ajaxUrl   = '<?php echo WooCommerce::ajax_url(); ?>';
                        ws_ajax_url     = '<?php echo WooCommerce::ajax_url(); ?>';
                </script>
            <?php
           }

           // Override delivery info
           public function override_delivery($order_id){
                if(strlen($_POST["wspup_id"])>0){
                    update_post_meta( $order_id, '_shipping_first_name', '');
                    update_post_meta( $order_id, '_shipping_last_name', '');
                    update_post_meta( $order_id, '_shipping_address_1', $_POST["wspup_address"]);
                    update_post_meta( $order_id, '_shipping_address_2', '');
                    update_post_meta( $order_id, '_shipping_company', $_POST["wspup_name"]);
                    update_post_meta( $order_id, '_shipping_city', $_POST["wspup_city"]);
                    update_post_meta( $order_id, '_shipping_postcode', $_POST["wspup_zip"]);
                }
           }

           // Is rate dynamic, Ajax
           public function check_rates(){
                // Get Rate
                $rate_id = mysql_escape_string($_REQUEST['rate_id']); 
                $api = $this->ws_api($this->options['api_key']);
                $rates = $api->GetShippingRates(); 
                $is_dyn_required = false; 

                // Loop through rates, and check if the rate is dyn
                if(is_array($rates)){
                    foreach($rates as $rate){
                        if($rate->dynamic_pickup && (("WS".$rate->id) == $rate_id)){
                            $is_dyn_required = true; 
                        }
                    }  
                }

                // Send response
                wp_send_json_success(array("rate" => $_REQUEST['rate_id'], "dyn" => $is_dyn_required));
           }


           // Order placed
           public function order_placed($order_id){
                global $wpdb;
                if(strlen($_POST["wspup_id"])>0){
                    $table_name = $wpdb->prefix . "webshipr";

                    if(is_array($_POST["shipping_method"])){
                            $rate_id = $_POST["shipping_method"][0];
                    }elseif(is_string($_POST["shipping_method"])){
                            $rate_id = $_POST["shipping_method"];
                    }else{
                            $rate_id = "not_known";
                    }

                    $wpdb->insert( $table_name, array( 'woo_order_id' => $order_id, 
                        'dynamic_pickup_identifier' => mysql_escape_string($_POST["wspup_id"]),
                        'shipping_method' => mysql_escape_string($rate_id),
                        'country_code' => mysql_escape_string($_POST["wspup_country"]),
                        'address' => mysql_escape_string($_POST["wspup_address"]),
                        'city' => mysql_escape_string($_POST["wspup_city"]),
                        'postal_code' => mysql_escape_string($_POST["wspup_zip"]),
                        'name' => mysql_escape_string($_POST["wspup_name"])
                    ));

                }



            }

           // Create Webshipr table to store pickup places for orders
           private function create_table(){
                global $wpdb;
                $table_name = $wpdb->prefix . "webshipr";
                $sql = "CREATE TABLE $table_name (
                        woo_order_id int not null, 
                        dynamic_pickup_identifier text not null,
                        shipping_method text not null,
                        country_code varchar(2) not null, 
                        address text not null, 
                        city text not null,
                        postal_code text not null,
                        name text not null
                    );";

                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
                dbDelta( $sql ); 
           }

           // Drop Webshipr table
           private function drop_table(){
                global $wpdb;
                $table_name = $wpdb->prefix . "webshipr";
                $sql = "DROP TABLE $table_name;";

                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
                dbDelta( $sql ); 
           }

           // Method to handle dynamic pickup places
           public function append_dynamic(){
                global $woocommerce;

                // Get selected rate id
                if(is_array($woocommerce->session->chosen_shipping_methods)){
    		       $rate_id = $woocommerce->session->chosen_shipping_methods[0]; 
    	        }elseif($_POST && is_array($_POST["shipping_method"])){
                   $rate_id = $_POST["shipping_method"][0];
                }elseif($_POST && is_string($_POST["shipping_method"])){
                   $rate_id = $_POST["shipping_method"];
                }else{
                   $rate_id = "not_known";
                }
                
                // Is it a webshipr rate at all?
                if(preg_match( "/WS/", $rate_id )){
                    $this_rate = $this->get_rate_details($rate_id);
                    
                    // If dynamic, load puptpl
                    if($this_rate->dynamic_pickup){
                        $rate = str_replace("WS","",$rate_id); 
                        require_once 'puptpl.php';
                    }
                
                }
           }

           // Get WS rate from WS shipping id
           private function get_rate_details($rate_id){
                $api = $this->ws_api($this->options['api_key']);
                if($api->CheckConnection()){
                    foreach($api->GetShippingRates() as $rate){
                        if( (int)$rate->id == (int)str_replace("WS","",$rate_id) ){
                            return $rate;
                        }
                    }
                }else{
                    return false;
                }
           }

            
            // White list our options using the Settings API
            public function admin_init() {
                register_setting('webshipr_options', $this->option_name, array($this, 'validate'));
            }

            // Add entry in the settings menu
            public function add_page() {
                add_options_page('webshipr options', 'Webshipr options', 'manage_options', 'webshipr_options', array($this, 'options'));
            }

            // Print the settings menupage itself
            public function options() {
                $options = get_option($this->option_name);
                ?>
                <div class="wrap">
                    <h2>Webshipr account options</h2>
                    <form method="post" action="options.php">
                        <?php settings_fields('webshipr_options'); ?>
                        <table class="form-table">
                            <tr valign="top"><th scope="row">Webshipr API key:</th>
                                <td><input type="text" name="<?php echo $this->option_name?>[api_key]" value="<?php echo $options['api_key']; ?>" /></td>
                            </tr>
                            <tr valign="top"><th scope="row">Autoprocess shipments</th>
                                <td>
                                    <input type="checkbox" name="<?php echo $this->option_name?>[auto_process]" <?php echo (int)$options['auto_process'] == 1 ? "checked" : "" ?> />
                                    <i>This setting is typically used if you have a warehouse integration. It means that it will automatically send the order to webshipr, when its created.</i>
                                </td>
                            </tr>
                            <tr valign="top"><th scope="row">Show swipbox settings</th>
                                <td>
                                    <input type="checkbox" name="<?php echo $this->option_name?>[swipbox]" <?php echo (int)$options['swipbox'] == 1 ? "checked" : "" ?> />
                                    <i>Shows parcel size for order. Needed for swipbox, if you deliver parcels directly in parcel stations.</i>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
                        </p>
                    </form>

                </div>
                <?php
            }

            // Validate settings
            public function validate($input) {

                $valid = array();
                $valid['api_key'] = sanitize_text_field($input['api_key']);
                $valid['auto_process'] = ($input['auto_process'] == 'on' ? true : false);
                $valid['show_search_address'] = ($input['show_search_address'] == 'on' ? true : false);
                $valid['show_address_bar'] = ($input['show_address_bar'] == 'on' ? true : false);
                $valid['swipbox'] = ($input['swipbox'] == 'on' ? true : false);

                $api = $this->ws_api($valid['api_key']);
                $check = $api->CheckConnection();

                if (!$check) {
                    add_settings_error(
                            'api_key',                  
                            'not_connected',            
                            'Please enter a valid key from your webshipr account. It can be found in your profile under the webshop.',
                            'error'
                    );
                    
                    $valid['api_key'] = $this->data['api_key'];
                }else{
                    add_settings_error(
                            'api_key', 
                            'connected',
                            'Congratulations! Shop is now connected as ' . $check->Shop_name,
                            'updated'
                    );
                }
                return $valid;
            }

            public function activate() {
                update_option($this->option_name, $this->data);
                $this->create_table();
            }

            public function deactivate() {
                //delete_option($this->option_name);
                //$this->drop_table();
                // Cleanup disabled - annoying for customers.
            }

            // Method to display webshipr on orders
            public function show_on_order(){
                global $wpdb;
                $api = $this->ws_api($this->options['api_key']);
                $woo_order = new WC_Order($_GET["post"]);
                $rates = $api->GetShippingRates($woo_order->get_total());
                
                // Depending on woocommerce version, get the shipping method
                if(method_exists($woo_order, 'get_shipping_methods')){
                        $arr = $woo_order->get_shipping_methods();
                        $woo_method_array = reset($arr);
                        $woo_order_id = $woo_method_array["method_id"];
                }else{
                        $woo_order_id = $woo_order->shipping_method;
                }

                $ws_rate_id = (preg_match("/WS/", $woo_order_id) ? str_replace("WS", "", $woo_order_id) : -1);

                $rate_name = $this->get_rate_name($ws_rate_id,$rates);

                // If user tried to process or reprocess - handle this. 
                if(isset($_GET["webshipr_process"])){
                    if($_GET["webshipr_process"] == 'true'){
                        $this->WooOrderToWebshipr($woo_order, $_GET["ws_rate"], (isset($_GET["swipbox"]) ? $_GET["swipbox"] : ''));
                    }
                }
                if(isset($_GET["webshipr_reprocess"])){
                    if($_GET["webshipr_reprocess"] == 'true'){
                        $api->SetAndProcessOrder($woo_order->id, $_GET["ws_rate"]);
                    }
                }

                

                echo '<div class="postbox" id="webshipr_backend" style="display:none;">';
                echo '<div class="handlediv" title="Click to toggle"><br></div>';
                echo '<h3 class="hndle">Webshipr status</h3>';
                echo '<div class="inside">';
                echo '<table style="margin-left: 10px;">';

                // Check if connected to webshipr
                if($api->CheckConnection()){

                    // Need this later again.
                    $exists = $api->OrderExists($woo_order->id);

                    // Check if order exists in WS
                    if($exists){

                        // Get informatins from API
                        $ws_order = $api->GetOrder($woo_order->id);
                        echo "<tr><td>Current status</td><td> ".$this->show_status($ws_order->status)."</td></tr>";
                            
                            // If order in Webshipr is dispatched, then...

                            if($ws_order->status == "dispatched" ||  $ws_order->status == "partly_dispatched"){
                                
                                if(strlen($ws_order->print_link)>1)
                                    echo "<tr><td>Print label </td><td><a href =\"". $ws_order->print_link . "\" target=\"_blank\">Click here</a></td></tr><br/>";
                                if($ws_order->print_return_link) 
                                    echo "<tr><td>Print return-label </td><td><a href =\"". $ws_order->print_return_link . "\" target=\"_blank\">Click here</a></td></tr><br/>";
                                if(strlen($ws_order->tracking_url)>1)
                                    echo "<tr><td>Tracking</td><td><a href =\"". $ws_order->tracking_url . "\" target=\"_blank\">Click here</a></td></tr>";

                                echo "<tr><td>Processed with: </td><td>".$this->get_rate_name($ws_order->shipping_rate_id,$rates)."</td></tr>";
                            }else if($ws_order->status == "partner_processing" || $ws_order->status == "pending_partner" || $ws_order->status == "partner_waiting_for_stock"){

                                echo "<tr><td>Processing with: </td><td>".$this->get_rate_name($ws_order->shipping_rate_id,$rates)."</td></tr>";
                            }else{

                                // Order is in webshipr, but not processed correctly

                                echo "<tr><td colspan=2>";
                                echo "<select name=\"ws_rate\" id =\"ws_rate\">";
                                        
                                foreach($rates as $rate){
                                    echo "<option value = ". $rate->id . (($rate->id == $ws_rate_id) ? " selected" : "").">".$rate->name."</option>";
                                }

                                echo "</select>&nbsp;";
                                echo "<a class=\"button button-primary\" onClick=\"reprocess_order()\"  href=\"#\">Re-process order</a>";

                                // This is already within a form, so we need a workaround to submit data for processing

                                echo "
                                    <script>
                                            function reprocess_order(){
                                            var e = document.getElementById(\"ws_rate\");
                                            var strId = e.options[e.selectedIndex].value;
                                            var cur_url = document.URL.split(\"&webshipr_process=true\")[0].split(\"&webshipr_reprocess=true\")[0];

                                            window.location = cur_url+\"&webshipr_reprocess=true&ws_rate=\"+strId;
                                        }
                                    </script>
                                ";
                                echo "</td></tr>";
                            
                            }
                    }else{
                        // Order does not exist - allow to process
                        echo "<tr><td colspan=2>";
                        echo "<h4>The order hasnt yet been sent to webshipr.</h4>";
                        
                        // Check if the rate was from webshipr
                        if($rate_name == 'not_known'){
                            echo "It wasnt bought with a rate from webshipr. But you can process it anyways:<br/>";
                        }else{
                            echo "It was bought in the store with '$rate_name' <br/>";
                        }
                        
                        echo "<select name=\"ws_rate\" id =\"ws_rate\">";

                                
                        foreach($rates as $rate){
                            echo "<option value = ". $rate->id . (($rate->id == $ws_rate_id) ? " selected" : "").">".$rate->name."</option>";
                        }

                        echo "</select>&nbsp;";

                        // Select swipbox size
                        if((int)$this->options['swipbox'] == 1){
                            echo '<select name="swipbox" id="swipbox">';
                            echo '<option value="1">Small</option>';
                            echo '<option value="2">Medium</option>';
                            echo '<option value="3">Large</option>';
                            echo '<option value="101">Oversize 1</option>';
                            echo '<option value="102">Oversize 2</option>';
                            echo '<option value="103">Oversize 3</option>';
                            echo '</select>';
                        }

                        echo "<a class=\"button button-primary\" onClick=\"process_order()\" href=\"#\">Process order</a>";

                        // This is already within a form, so we need a workaround to submit data for processing

                        echo "
                            <script>
                                    function process_order(){

                                        var e = document.getElementById(\"ws_rate\");
                                       
                                        var strId = e.options[e.selectedIndex].value;
                                        var s = document.getElementById(\"swipbox\");

                                        if(s !== null){
                                            var strS = s.options[s.selectedIndex].value;
                                        }
                                        var cur_url = document.URL.split(\"&webshipr_process=true\")[0].split(\"&webshipr_reprocess=true\")[0];

                                        if(s !== null){
                                            window.location = cur_url+\"&webshipr_process=true&ws_rate=\"+strId+\"&swipbox=\"+strS;
                                        }else{
                                            window.location = cur_url+\"&webshipr_process=true&ws_rate=\"+strId;
                                        }
                                    }
                            </script>
                        ";
                        echo "</td></tr>";
                    }



                    // Append view for Dynamic address if its used
                    $dynamic_order = $wpdb->get_row("SELECT * FROM ".$this->ws_table()." WHERE woo_order_id = ".$woo_order->id);
                    
                    if($dynamic_order){

                        echo "<tr><td colspan=2><br/></td></tr>";
                        echo "<tr>";
                        echo "<td colspan=2>";

                        // Check which text is appropriate to the situation
                        if($exists){
                            if($ws_order->status == "dispatched" && (int)$ws_order->shipping_rate_id == (int)$ws_rate_id){
                                echo "<b>Shipment processed with ' $rate_name ' and following pickup address<b>";
                            }else if($ws_order->status == "dispatched" && (int)$ws_order->shipping_rate_id != (int)$ws_rate_id){
                                echo "<b>Shipment was processed with another rate. Following pickup address is therefore not used.<b>";
                            }else{
                                echo "<b>Pickup place selected ( - will be used if processed with ' $rate_name ' )<b>";
                            }
                        }else{
                            echo "<b>Pickup place selected ( - will be used if processed with ' $rate_name ' )<b>";
                        }

                        echo "</td>";
                        echo "</tr><tr>";
                        echo "<td>Name</td>";
                        echo "<td>".$dynamic_order->name."</td>";
                        echo "</tr><tr>";
                        echo "<td>Address</td>";
                        echo "<td>".$dynamic_order->address."</td>";
                        echo "</tr><tr>";
                        echo "<td>City</td>";
                        echo "<td>".$dynamic_order->postal_code." " .$dynamic_order->city. "</td>";
                        echo "</tr><tr>";
                        echo "<td>Country</td>";
                        echo "<td>".$dynamic_order->country_code."</td>";
                        echo "</tr>";
                    }

                }else{
                    echo "<tr><td colspan=2>";
                    echo "Currently not connected to Webshipr. Please check your API Key under options.";
                    echo "</td></tr>";
                }
                echo "</table>";
                echo "</div>";
                echo "</div>";
            }

            // Get tbl name
            private function ws_table(){
                global $wpdb;
                return $wpdb->prefix . "webshipr";
            }

            // Get rate name from id, if webshipr rate
            private function get_rate_name($rate_id,$rates){
                if(is_array($rates)){
                    foreach($rates as $rate){
                        if((int)$rate->id == (int)$rate_id)
                            return $rate->name;
                    }
                }
                return "not_known";
            }

            // Get API instance
            private function ws_api($key){
                return new WebshiprAPI(API_RESOURCE, $key);
            }

            // Method to create the order in Webshipr
            private function WooOrderToWebshipr($woo_order, $rate_id, $swipbox = null){

                global $wpdb;

                $items = $woo_order->get_items();

                // Create Items and collect info
                $ws_items = array();
                

                foreach($items as $item){

                    $weight_uom = get_option('woocommerce_weight_unit');

                    if($weight_uom == 'kg'){
                        if((int)$item["variation_id"] > 0){
                            $variation = new WC_Product_Variation($item["variation_id"]);
                            $weight = (double)$variation->get_weight()*(double)$item["qty"]*1000;
                        }else{
                            $weight = (double)get_product($item["product_id"])->get_weight()*(double)$item["qty"]*1000;
                        }
                    }else{
                        if((int)$item["variation_id"] > 0){
                            $variation = new WC_Product_Variation($item["variation_id"]);
                            $weight = (double)$variation->get_weight()*(double)$item["qty"];
                        }else{
                            $weight = (double)get_product($item["product_id"])->get_weight()*(double)$item["qty"];
                        }
                    }

                    $product = new WC_Product($item["product_id"]);
                    $location = get_post_meta( $item["product_id"], '_webshipr_location', true );
                    $ws_items[] = new ShipmentItem($product->get_sku(), $item["name"], $item["product_id"], $item["qty"], "pcs", $weight, $location);
                    
                }



                // Billing Address 
                $bill_adr = new ShipmentAddress();
                $bill_adr->Address1 = $woo_order->billing_address_1;
                $bill_adr->Address2 = $woo_order->billing_address_2;
                $bill_adr->City = $woo_order->billing_city;
                $bill_adr->ContactName = $woo_order->billing_company;
                $bill_adr->ContactName2 = $woo_order->billing_first_name . " " . $woo_order->billing_last_name;
                $bill_adr->CountryCode = $woo_order->billing_country;
                $bill_adr->EMail = $woo_order->billing_email;
                $bill_adr->Phone = $woo_order->billing_phone;
                $bill_adr->ZIP = $woo_order->billing_postcode;


                // Delivery Address
                $deliv_adr = new ShipmentAddress();
                $deliv_adr->Address1 = $woo_order->shipping_address_1;
                $deliv_adr->Address2 = $woo_order->shipping_address_2;
                $deliv_adr->City = $woo_order->shipping_city;
                $deliv_adr->ContactName = $woo_order->shipping_company;
                $deliv_adr->ContactName2 = $woo_order->shipping_first_name . " " . $woo_order->shipping_last_name;
                $deliv_adr->CountryCode = $woo_order->shipping_country;
                $deliv_adr->EMail = $woo_order->billing_email;
                $deliv_adr->Phone = $woo_order->billing_phone;
                $deliv_adr->ZIP = $woo_order->shipping_postcode;


                // Create the shipment
                $shipment = new Shipment();
                $shipment->BillingAddress   = $bill_adr;
                $shipment->DeliveryAddress  = $deliv_adr;
                $shipment->Items            = $ws_items;
                $shipment->ExtRef           = $woo_order->id;
                $shipment->ShippingRate     = $rate_id;
                $shipment->SubTotalPrice    = $woo_order->order_total - $woo_order->order_shipping - $woo_order->order_shipping_tax - $woo_order->order_tax;
                $shipment->TotalPrice       = $woo_order->order_total - $woo_order->order_shipping - $woo_order->order_shipping_tax;
                $shipment->Currency         = get_woocommerce_currency();
                $shipment->swipbox_size     = $swipbox; 


                // Check if the order has a dynamic address
                $dynamic_order = $wpdb->get_row("SELECT * FROM ".$this->ws_table()." WHERE woo_order_id = ".$woo_order->id);
                if($dynamic_order){

                    // reset email and phone for delivery 
                    $deliv_adr->EMail = '';
                    $deliv_adr->Phone = '';

                    // define dyn adr
                    $dynamic_adr                = new ShipmentAddress();
                    $dynamic_adr->Address1      = $dynamic_order->address;
                    $dynamic_adr->City          = $dynamic_order->city;
                    $dynamic_adr->ContactName   = $dynamic_order->name;
                    $dynamic_adr->ZIP           = $dynamic_order->postal_code;
                    $dynamic_adr->CountryCode   = $dynamic_order->country_code;
                    
                    $shipment->custom_pickup_identifier = $dynamic_order->dynamic_pickup_identifier;
                    $shipment->DynamicAddress = $dynamic_adr;
                }

                

                // Send the order
                $api = $this->ws_api($this->options['api_key']);
                $api->CreateShipment($shipment);
            }

            // Render webshipr status 
            private function show_status($status){
                 switch ($status)
                 {
                    case "not_recognized":
                        return "<font style='color: red;'>Not recognized</font><br/> <i>(usually means the shipment wasnt processed with a rate from webshipr)</i>";
                        break;
                    case "choose_size":
                        return "<font style='color: red;'>Choose size</font><br/> <i>(SwipBox needs to know the size of your parcel. Select size in webshipr.)</i>";
                        break;
                    case "country_rejected":
                        return "<font style='color: yellow;'>Country rejected</font><br/> <i>( Means the shipping rate is configured to deny this country in webshipr )</i>";
                        break;
                    case "dispatched": 
                        return "<font style='color: green; font-weight: bold;'>Sent to shipper</font>";
                        break;
                    case "carrier_error":
                        return "<font style='color: red; font-weight: bold;'>Carrier error</font><br/> <i>Please check the credentials for the carrier in webshipr, or contact Webshipr support</i>";
                        break;
                    case "error_processing":
                        return "<font style='color: red; font-weight: bold;'>Carrier error</font><br/> <i>Please check the credentials for the carrier in webshipr, or contact Webshipr support</i>";
                        break;
                    case "disabled":
                        return "<font style='color: red; font-weight: bold;'>Subscription disabled</font> <i> Please check if all your invoices are paid on your webshipr subscription </i>";
                        break;
                    case "not_processed":
                        return "<font style='color: red; font-weight: bold;'>Not processed</font> <i> System error - please contact webshipr! </i>";
                        break;
		            case "limit_exceeded":
		    	        return "<font style='color: red; font-weight: bold;'>Limit exceeded</font> <i> please upgrade your subscription! </i>";
                        break;
                    case "partner_processing":
                        return "<font style='color: blue; font-weight: bold;'>Partner processing</font> <i> Partner is currently processing. </i>";
		    	        break;
                    case "pending_partner":
                        return "<font style='color: blue; font-weight: bold;'>Pending partner</font> <i> Waiting for partner to process. </i>";
                        break;
                    case "partner_waiting_for_stock": 
                        return "<font style='color: yellow; font-weight: bold;'>Partner waiting for stock</font> <i> Partner is waiting for stock. </i>";
                        break;
                    case "partly_dispatched":
                        return "<font style='color: yellow; font-weight: bold;'>Partly dispatched</font> <i> Shipment is partly dispatched. </i>";
                        break;
                    default: 
                        return $status;
                        break;
                 }
            }
        }
         
        // finally instantiate plugin class and add it to the set of globals
         
        $GLOBALS['WebshiprWC'] = new WebshiprWC();
    }


    // Shipping
     
    function shipping_method_init() {
        if ( ! class_exists( 'WebshiprRates' ) ) {
            class WebshiprRates extends WC_Shipping_Method {
                private $options;

                // Constructor
                public function __construct() {
                    $this->options = get_option('webshipr_options');
                    $this->init("WS", "Webshipr", "Webshipr calculates shipping rates autmatically and live directly from your Webshipr.com account.<br/>
                        If you experience any issues, please contact support@webshipr.com.<br/><br/>
                        If you want to disable the webshipr shipping, please disable the plugin, under the plugins menu.");
                }
                 

                function init($id, $title, $description) {
                    $this->id = $id;
                    $this->method_title = $title;
                    $this->method_description = $description;
                    $this->title = $title;
                    $this->enabled = "yes";
                    

                    // Load the settings API
                    $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
                    $this->init_settings(); // This is part of the settings API. Loads settings you previously init.
                     
                    // Save settings in admin if you have any defined
                    add_action( 'woocommerce_update_options_shipping_' . $id, array( $this, 'process_admin_options' ) );
                }
                 

                // Calculate shipping rates
                public function calculate_shipping( $package ) {
                    global $woocommerce;
		            $total = 0; 
                    $coupon_free_shipping = false; 

    	            // Calculate cart total incl. taxes
        		    if(count($package["contents"] > 0)){
            			foreach($package["contents"] as $content){
            		    		$total += $content["line_total"] + $content["line_tax"];
            			}
        		    }
                    
                    // Check if any coupon codes are applied
                    if(count($package['applied_coupons']) > 0){
                        foreach($package['applied_coupons'] as $coupon){
                                $obj = new WC_Coupon($coupon);
                                $total = $total - $obj->amount;

                                // check if coupon grants free shipping
                                if($obj->free_shipping == 'yes'){
                                    $coupon_free_shipping = true; 
                                }
                        }
                    }

                    $api = $this->ws_api($this->options['api_key']);
                    $rates = $api->GetShippingRates($total);
                    $destination  = $package["destination"]["country"];
                    
                    $weight_uom = get_option('woocommerce_weight_unit');

                    // Webshipr wants UOM in grams
                    if ($weight_uom == 'kg'){
                        $cart_weight = $woocommerce->cart->cart_contents_weight * 1000;
                    }else{
                        $cart_weight = $woocommerce->cart->cart_contents_weight;
                    }


                    // If any rates were found
                    if($rates){
                        foreach($rates as $rate){
                            if($this->country_accepted($rate, $destination) && 
                                $rate->max_weight >= $cart_weight && $rate->min_weight <= $cart_weight){
                                 
                                // Make and add the rate
                                $new_rate = array(
                                    'id' => "WS".$rate->id,
                                    'label' => $rate->name,
                                    'cost' => ($coupon_free_shipping ? 0 : $rate->price ),
                                    'taxes' => ($rate->tax_percent > 0 ? '' : false), 
                                    'calc_tax' => 'per_order'
                                );
                                $this->add_rate( $new_rate );
                            }
                        }
                    }
                }

                // Method to validate rate from country id
                private function country_accepted($rate, $cur_country){
                        $result = false;
                        foreach($rate->accepted_countries as $country){
                                if($country->code == $cur_country || $country == 'ALL'){
                                        $result = true;
                                }                       
                        }
                        return $result;
                }

                // Return Webshipr API object
                private function ws_api($key){
                    return new WebshiprAPI(API_RESOURCE, $key);
                }
            }
        }
    }
         
    add_action( 'woocommerce_shipping_init', 'shipping_method_init' );
         
    function add_shipping( $methods ) {
        $methods[] = 'WebshiprRates';
        return $methods;
    }
     
    add_filter( 'woocommerce_shipping_methods', 'add_shipping' );




}
?>
