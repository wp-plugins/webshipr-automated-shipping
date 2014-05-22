
var ws_ajax_url = ""; 
// Bool if dynamic rate is picked
function dynamic_picked(){

	var selected_method = jQuery(".shipping_method:checked").val();
	var result = false; 
	jQuery.ajax({
         type : "post",
         dataType : "json",
         async: false, 
         url : ws_ajax_url,
         data : {action: "check_rates", rate_id: selected_method},
         success: function(response) {
         	if(response.data.dyn){
         		result = true;   
         	}
         }
    });
    return result; 
}


// Is a shop selected?
function shop_selected(){
	if(jQuery("#dynamic_destination_select :selected") === undefined){
		var selected_pickup = false; 
	}else{
		if(jQuery("#dynamic_destination_select :selected").val() === undefined){
			var selected_pickup = false; 
		}else{
			var selected_pickup = true; 
		}
	}
	return selected_pickup; 
}




jQuery(function(){

	jQuery("#webshipr_backend").insertAfter("#woocommerce-order-data");
	jQuery("#webshipr_backend").show();

	jQuery(document).ready(function(){

		// Listen on blur for phone
		jQuery("#billing_phone").blur(function(){
    			jQuery("body").trigger('update_checkout');
		});

	

		jQuery("#place_order").live('click',function(e){

			// Figure out if a pickup address is defined
			if(dynamic_picked() === true && shop_selected() === false){
				e.preventDefault(); 
				jQuery("body").trigger('update_checkout');
				jQuery('html,body').animate({
						 scrollTop: jQuery("#order_review_heading").offset().top},
				'slow');
				alert("Venligst vælg afhentningssted, eller en anden forsendelsesmetode.");
			}
		}); 
	
	});





	jQuery(document).on('click',jQuery('#dynamic_destination_select'), function(){
        jQuery("#dynamic_destination_select").change(function(){
            jQuery(".service_point").hide();
            jQuery("#servicepoint_"+jQuery("#dynamic_destination_select").val()).show();
        });
	}); 




});

function set_selection(){
    jQuery(".service_point").hide();
    jQuery("#servicepoint_"+jQuery("#dynamic_destination_select").val()).show();
}

