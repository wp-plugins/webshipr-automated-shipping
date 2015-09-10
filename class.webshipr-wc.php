<?php
require_once("class.webshipr-order-html.php");

if ( ! class_exists( 'WebshiprWC' ) ) {

    class WebshiprWC {

       protected $option_name = 'webshipr_options';
       protected $data = array(
         'api_key' => '',
         'auto_process' => false, 
         'show_search_address' => true, 
         'show_address_bar' => true
       );
    
       public $options;  


       // Constructor to be initialized
       public function __construct() {

            global $woocommerce;                

            // Backend order view
            add_action('woocommerce_admin_order_data_after_order_details', array($this,'show_on_order'));

            // Hook backend admin stuff
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_menu', array($this, 'add_page'));

            // Hook cart PUP content
            add_action('woocommerce_review_order_before_order_total', array($this,'append_dynamic'));

            // Register and load JS and CSS
            add_action('wp_loaded', array($this, 'register_frontend')); 
            add_action('admin_enqueue_scripts', array($this, 'register_backend')); 
            add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_backend'));

            // Hook autoprocess
            add_action('woocommerce_checkout_order_processed', array($this, 'order_placed'));

            // Hook on update order meta to ensure correct delivery address
            add_action('woocommerce_checkout_update_order_meta', array($this, 'override_delivery'));

            // Hook to do checkout validation
            add_action('woocommerce_checkout_process', array($this, 'validate_on_process')); 

            // Autoprocess
            add_action('woocommerce_payment_complete', array($this, 'auto_process'));

            // Hook ajax methods
            add_action( 'wp_ajax_nopriv_check_rates', array($this, "check_rates"));
            add_action( 'wp_ajax_check_rates', array($this, 'check_rates') );
            add_action( 'woocommerce_after_checkout_shipping_form', array($this, 'set_ajaxurl')); 
            add_action( 'wp_ajax_nopriv_get_shops', array($this, "ajax_get_shops"));
            add_action( 'wp_ajax_get_shops', array($this, 'ajax_get_shops') );
            

            // Localization 
            load_plugin_textdomain('WebshiprWC', false, basename( dirname( __FILE__ ) ) . '/languages' );

            // Initialize settings
            $this->options = get_option('webshipr_options');

            // Activate
            register_activation_hook(__FILE__, array($this,'activate'));
       }


       // Register JS scripts
       function register_frontend(){

            // Register CSS
            wp_register_style("ws_css", plugins_url("css/wspup.css", __FILE__));
            
            // Register JS
            wp_register_script("ws_maps", "https://maps.googleapis.com/maps/api/js?sensor=false"); 
            wp_register_script("ws_maplabel", plugins_url("js/maplabel.js", __FILE__));
            wp_register_script("ws_pup", plugins_url("js/wspup.js", __FILE__), array('jquery'));
            wp_register_script("ws_js", plugins_url("js/webshipr.js", __FILE__), array('jquery'));

       }

       function register_backend(){

          // Register backend JS
          wp_register_script("ws_backend_js", plugins_url("/js/ws_backend.js", __FILE__)); 

       }

       // Enqueue scripts
       function enqueue_frontend(){

            // CSS
            wp_enqueue_style('ws_css');

            // JS
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
            wp_enqueue_script('ws_backend_js');

            // Hook ajax stuff
            wp_localize_script( 'check_rates', 'wsAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ))); 
            wp_localize_script( 'get_shops', 'wsAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ))); 

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
            $api = $this->ws_api(); 

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
            
            $api = $this->ws_api();
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
            
           // Add error
           if($is_dyn_required && strlen($_REQUEST["wspup_id"]) == 0){
                wc_add_notice( __('Select a pickup point to proceed' , 'WebshiprWC'), "error"); 
           }

       }

       // Set ajax url in checkout
       public function set_ajaxurl(){
            global $woocommerce;
        ?>
            <script type="text/javascript">
                    wspup.ajaxUrl   = '<?php echo $woocommerce->ajax_url(); ?>';
                    ws_ajax_url     = '<?php echo $woocommerce->ajax_url(); ?>';
            </script>
        <?php
       }

       // Override delivery info
       public function override_delivery($order_id){

          // Some themes started to not register any delivery address. 
          $order = new WC_Order($order_id);

          // If PUP Shipment
          if(isset($_POST["wspup_id"]) && strlen($_POST["wspup_id"])>0){

            // If delivery present - update. If not add
            if(isset($order->shipping_address_1) && strlen($order->shipping_address_1) > 0){
              
              update_post_meta( $order_id, '_shipping_address_1', $_POST["wspup_address"]);
              update_post_meta( $order_id, '_shipping_address_2', '');
              update_post_meta( $order_id, '_shipping_company', $_POST["wspup_name"]);
              update_post_meta( $order_id, '_shipping_city', $_POST["wspup_city"]);
              update_post_meta( $order_id, '_shipping_postcode', $_POST["wspup_zip"]);

            }
         } 

       }

       // Is rate dynamic, Ajax
       public function check_rates(){
            // Get Rate
            $rate_id = esc_sql($_REQUEST['rate_id']); 
            $api = $this->ws_api();
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
            
            if(isset($_POST["wspup_id"]) && strlen($_POST["wspup_id"])>0){
                // Save pickup point id
                add_post_meta($order_id, 'wspup_pickup_point_id', $_POST["wspup_id"]);
            }
            
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
            $api = $this->ws_api();
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
        }

        public function deactivate() {
            //delete_option($this->option_name);
            //$this->drop_table();
            // Cleanup disabled - annoying for customers.
        }

        // Method to display webshipr on orders
        public function show_on_order(){

            $wooOrder = new WC_Order($_GET["post"]);
            
            // If user tried to process or reprocess - handle this. 
            if(isset($_GET["webshipr_process"])){
                if($_GET["webshipr_process"] == 'true'){
                    $this->WooOrderToWebshipr($wooOrder, $_GET["ws_rate"], (isset($_GET["swipbox"]) ? $_GET["swipbox"] : ''));
                }
            }
            if(isset($_GET["webshipr_reprocess"])){
                if($_GET["webshipr_reprocess"] == 'true'){
                    $api = $this->ws_api(); 
                    $this->UpdateWebshiprOrder($wooOrder, $_GET["ws_rate"], (isset($_GET["swipbox"]) ? $_GET["swipbox"] : ''));
                }
            }

            

            $orderHtml = new WebshiprOrderHtml($wooOrder);
            $orderHtml->RenderHTML(); 

        }

        // Get API instance
        public function ws_api($key = false){
            if(!$key)
              $key = $this->options['api_key']; 
            return new WebshiprAPI(API_RESOURCE,$key);
        }

        // Method to create the order in Webshipr
        private function WooOrderToWebshipr($woo_order, $rate_id, $swipbox = null){

            // Generate shipment
            $shipment = $this->prepareShipment($woo_order, $rate_id, $swipbox); 

            // Send the order
            $api = $this->ws_api();
            $api->CreateShipment($shipment);

        }

        // Mehtod to update order in webshipr
        private function UpdateWebshiprOrder($woo_order, $rate_id, $swipbox){
           
            // Generate shipment
            $shipment = $this->prepareShipment($woo_order, $rate_id, $swipbox); 

            // Update the order
            $api = $this->ws_api(); 
            $api->UpdateShipment($shipment); 
        
        }

        // Build shipment object prepared for the API
        private function prepareShipment($woo_order, $rate_id, $swipbox){
            $items = $woo_order->get_items();

            // Create Items and collect info
            $ws_items = array();
            

            // Append items
            foreach($items as $item){

                // Get apropriate product info 
                if((int)$item["variation_id"] > 0){
                  $product = new WC_Product_Variation($item["variation_id"]); // Variation inherits Product
                }else{
                  $product = new WC_Product($item["product_id"]);
                }

                // Get apropriate weight
                $weight_uom = get_option('woocommerce_weight_unit');
                $weight_multiplier = $weight_uom == 'kg' ? 1000 : 1; 
                $weight = (double)$product->get_weight() * (double)$item["qty"] * $weight_multiplier;

                // Add items
                $ws_items[] = new ShipmentItem( $this->getSku($product->get_sku()), 
                                                $item["name"], 
                                                $item["product_id"], 
                                                $item["qty"], 
                                                "pcs", 
                                                $weight, 
                                                $this->getLocation($product->get_sku()) );
                
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

            // Woo has started to only offer billing adr some times # For some weird reason strlen has been removed at this time?!
            if(!$deliv_adr->Address1 ||count(str_split((string)$deliv_adr->Address1)) == 0){
              $deliv_adr = $bill_adr;
            }


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
            $shipment->Comment          = $woo_order->customer_message; 

            // Check if the order has a dynamic address
            $pickup_point_id = get_post_meta($woo_order->id, 'wspup_pickup_point_id', true); 

            if(isset($pickup_point_id) && strlen($pickup_point_id)>0){

                // Reset email and phone for delivery 
                $deliv_adr->EMail = '';
                $deliv_adr->Phone = '';

                // Define dyn adr
                $dynamic_adr                = new ShipmentAddress();
                $dynamic_adr->Address1      = $woo_order->shipping_address_1;
                $dynamic_adr->City          = $woo_order->shipping_city;
                $dynamic_adr->ContactName   = $woo_order->shipping_company;
                $dynamic_adr->ZIP           = $woo_order->shipping_postcode;
                $dynamic_adr->CountryCode   = $woo_order->shipping_country;
                
                $shipment->custom_pickup_identifier = $pickup_point_id; 
                $shipment->DynamicAddress = $dynamic_adr;
            }

            return $shipment; 
        }

        /* 
         * In order to satisfy requirements of seperating SKU from Stock locations we have decided to 
         * put both in SKU. This because there is no nice way to add additional attributes to Product Variations.  
         * Therefore the SKU can be seperated from Locations by using "SKU - LOC"
         */ 

        // Get SKU by SKU Field
        private function getSku( $sku ){
                if( isset( $sku ) ){
                        $spl = explode( '-', $sku );
                        return $spl[0];
                }else{
                        return "";
                }
        }

        // Get Location by SKU Field
        private function getLocation( $sku ){
                if( isset( $sku ) ){
                        $spl = explode( '-', $sku );
                        return end( $spl );
                }else{
                        return "";
                }
        }


    }
}
// Add to globals
$GLOBALS['WebshiprWC'] = new WebshiprWC();

?>