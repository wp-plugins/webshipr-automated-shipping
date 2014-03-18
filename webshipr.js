jQuery(function(){

	jQuery("#webshipr_backend").insertAfter("#woocommerce-order-data");
	jQuery("#webshipr_backend").show();

	jQuery(document).on('click',jQuery('#dynamic_destination_select'), function(){
                jQuery("#dynamic_destination_select").change(function(){
                        jQuery(".service_point").hide();
                        jQuery("#servicepoint_"+jQuery("#dynamic_destination_select").val()).show();
                });
	}); 
});
        function set_selection(){
                jQuery("#dynamic_destination_select").change(function(){
                        jQuery(".service_point").hide();
                        jQuery("#servicepoint_"+jQuery("#dynamic_destination_select").val()).show();
                });
        }

