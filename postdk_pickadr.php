<?php 

	$api 	 = $this->ws_api($this->options['api_key']);
	parse_str($_POST["post_data"], $post_data);
	
	// Check if specific shipping address specified
	if((string)$post_data["shiptobilling"] == "1" || (string)$post_data["ship_to_different_address"] != "1"){
		$street  = preg_split('/(?=\d)/', $_POST["address"]);
		$street  = $street[0];
		$number  = filter_var($_POST["address"], FILTER_SANITIZE_NUMBER_INT);
		$country = $_POST["country"];
		$postal  = $_POST["postcode"];
	}else{
		$street  = preg_split('/(?=\d)/', $post_data["shipping_address_1"]);
		$street  = $street[0];
		$number  = filter_var($post_data["shipping_address_1"], FILTER_SANITIZE_NUMBER_INT);
		$country = $post_data["shipping_country"];
		$postal  = $post_data["shipping_postcode"];
	}

	$shops   = $api->GetPostDKShops($country, $street, $number, $postal);


	// Methods to write days in JS
	function write_day($day, $shop){
		$return = "";
		if (count($shop->openingHours)==0){ return "Ej tilgængelig"; }

		foreach($shop->openingHours as $curDay){
			if(strtoupper($curDay->day) == strtoupper($day)){
				$count = 1; 
				while($count>0){
					$strFromDay = "from$count";
					$strToDay   = "to$count";
					if(isset($curDay->$strFromDay) && isset($curDay->$strToDay)){
						$return .= ($return == "" ? $curDay->$strFromDay."-".$curDay->$strToDay : "<br/>".$curDay->$strFromDay."-".$curDay->$strToDay);
						$count++;
					}else{
						$count = -1;
					}
				}
			}
		}
		return ($return == "" ? "Lukket" : $return);
	}
?>
<tr>
		<th colspan="2"> Vælg nærmeste afhentningssted </th>
</tr>
<tr>
		<td colspan="2"> 
			<select name="dynamic_destination" id="dynamic_destination_select" style="width: auto;">
				<?php foreach($shops->servicePoints as $shop){ ?>
					<option value="<?php echo $shop->servicePointId?>">
						<?php echo $shop->name ?> - <?php echo $shop->visitingAddress->streetName ?>
						<?php echo $shop->visitingAddress->streetNumber?> (<?php echo $shop->visitingAddress->postalCode ?>)
					</option>
				<?php } ?>
			</select>
			<div id="pickup_info">

			</div>
				<script>
					function get_opening_hours(id){
						var shops = {
						<?php
						$count = 1;
						foreach($shops->servicePoints as $shop){
							$delimiter = (count($shops->servicePoints) == $count ? "" : ",");
						?>
							<?php echo $shop->servicePointId; ?>: {
																	"MO": "<?php echo write_day('MO', $shop);?>", 
																	"TU": "<?php echo write_day('TU', $shop);?>",
																	"WE": "<?php echo write_day('WE', $shop);?>", 
																	"TH": "<?php echo write_day('TH', $shop);?>", 
																	"FR": "<?php echo write_day('FR', $shop);?>", 
																	"SA": "<?php echo write_day('SA', $shop);?>", 
																	"SU": "<?php echo write_day('SU', $shop);?>",
																	"street": "<?php echo $shop->deliveryAddress->streetName." ".$shop->deliveryAddress->streetNumber?>",
																	"postal_code": "<?php echo $shop->deliveryAddress->postalCode ?>",
																	"country": "<?php echo $shop->deliveryAddress->countryCode ?>",
																	"city":"<?php echo $shop->deliveryAddress->city ?>",
																	"name":"<?php echo $shop->name ?>",
																	"count":  <?php echo count($shop->openingHours);?>
																	 
																   }<?php echo $delimiter; ?>

						<?php
							$count++;
						}
						?>
						}
						return shops[id];
					}

					function set_selection_hours(id){
						var str = "";
						var data = get_opening_hours(id); 

						if(data["count"] > 0){
							
							str += "<h3>Åbningstider for valgte udleveringssted</h3>";
							str += "<table>";
							str += "<tr>";
							str += "<th>Mandag</th><td>" + data["MO"] + "</td>";
							str += "</tr><tr>";
							str += "<th>Tirsdag</th><td>" + data["TU"] + "</td>";
							str += "</tr><tr>";
							str += "<th>Onsdag</th><td>" + data["WE"] + "</td>";
							str += "</tr><tr>";
							str += "<th>Torsdag</th><td>" + data["TH"] + "</td>";
							str += "</tr><tr>";
							str += "<th>Fredag</th><td>" + data["FR"] + "</td>";
							str += "</tr><tr>";
							str += "<th>Lørdag</th><td>" + data["SA"] + "</td>";
							str += "</tr><tr>";
							str += "<th>Søndag</th><td>" + data["SU"] + "</td>";
							str += "</tr>";
							str += "</table>";

						}

						str += "<input type='hidden' name='dyn_street' value='"+data["street"]+"' />";
						str += "<input type='hidden' name='dyn_postal_code' value='"+data["postal_code"]+"' />";
						str += "<input type='hidden' name='dyn_country' value='"+data["country"]+"' />";
						str += "<input type='hidden' name='dyn_city' value='"+data["city"]+"' />";
						str += "<input type='hidden' name='dyn_name' value='"+data["name"]+"' />";

						jQuery("#pickup_info").html(str);
					}

					jQuery("#dynamic_destination_select").change(function(){
						set_selection_hours(jQuery(this).find(":selected").val());
					});
					jQuery(document).ready(function(){
						set_selection_hours(jQuery("#dynamic_destination_select").find(":selected").val());
						var int=self.setInterval(set_selection_hours(jQuery("#dynamic_destination_select").find(":selected").val()), 3000);
					});
				</script>
		</td>

</tr>

