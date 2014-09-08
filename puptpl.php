<div id="wspup_wrapper">
	<h1>Vælg nærmeste afhentningssted</h1>
	<div id="wspup_container">
		<div id="wspup_zipbox">
			<input  class="wspup_input" id="wspup_address" placeholder="Adresse">  
			<input  class="wspup_input" id="wspup_zip" placeholder="Postnr."> 
			<input type="button" class="button" value="Søg" id="wspup_search_btn">
		</div>
		<div id="wspup_noresults">
			<br>
			<h3>Vi kunne desværre ikke finde nogen afhentningssteder. Prøv igen!</h3>
			<br>
		</div>
		<div id="wspup_loader">
			<img src="<?php echo plugins_url("img/ajax-loader.gif", __FILE__) ?>">

			<h3>Vi leder efter de nærmeste afhentningssteder...</h3>
			<br>
		</div>
		<div id="wspup_search_now">
			<br>
			<h3>Brug søgefeltet ovenfor og find et afhentningssted nær dig</h3>
		</div>
		<div id="wspup_results">
			<div id="wspup_select_list">
				<ol>
	
				</ol>
			</div>
			<div id="wspup_map" style="box-sizing: content-box;">

			</div>
			<div id="accept_button" style="box-sizing: content-box;">
					<p>Bekræft afhentningssted</p>
					<p>Klik her</p>
			</div>

		</div>
	</div>
	<span class="pup_close">
	Luk vindue [X]
	</span>

	<span class="wspup_watermark">
		Module by <a href="http://www.webshipr.com" target="_blank">www.webshipr.com</a>
	</span>
</div>

<div class="wspup_cart">
	<input type="button" class="button" value="Vælg afhentningssted" id="wspup_show_btn" onClick="wspup.showPup();wspupTransferAddress();">
	<input type="hidden" name="wspup_name">
	<input type="hidden" name="wspup_address">
	<input type="hidden" name="wspup_zip">
	<input type="hidden" name="wspup_city">
	<input type="hidden" name="wspup_country">
	<input type="hidden" name="wspup_id">
	<input type="hidden" name="wspup_carrier">
	<div id="wspup_selected_text"></div>
</div>


<script type="text/javascript">
	jQuery("#wspup_wrapper").appendTo("body");
	wspup.currentRateId = <?php echo $rate; ?>; 
	function wspupTransferAddress(){

		if(jQuery("#wspup_address").val().length === 0 && jQuery("#wspup_zip").val().length === 0){
			if(jQuery("#shipping_address_1").val().length>0){
				jQuery("#wspup_address").val(jQuery("#shipping_address_1").val());
				jQuery("#wspup_zip").val(jQuery("#shipping_postcode").val());
			}else{
				jQuery("#wspup_address").val(jQuery("#billing_address_1").val());
				jQuery("#wspup_zip").val(jQuery("#billing_postcode").val());
			}
		

			if(jQuery("#wspup_zip").val().length>0){
				wspup.search();
			}
		}
	}
</script>