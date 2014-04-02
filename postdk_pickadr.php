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
		<td colspan="2" > 
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
			<?php
				$count = 1;
				foreach($shops->servicePoints as $shop){
					if(count($shop->openingHours)>0){
				?>

					<div class="service_point" id="servicepoint_<?php echo $shop->servicePointId; ?>" onchange="set_selection()" <?php echo ($shop != $shops->servicePoints[0] ? "style=\"display: none;\"" : "")?>>
						<h3>Åbningstider for <?php echo $shop->name ?></h3>
						<table>
							<tr>
								<th>Mandag</th><td><?php echo write_day('MO', $shop);?></td>
							</tr>
							<tr>
								<th>Tirsdag</th><td><?php echo write_day('TU', $shop);?></td>
							</tr>
							<tr>
								<th>Onsdag</th><td><?php echo write_day('WE', $shop);?></td>
							</tr>
							<tr>
								<th>Torsdag</th><td><?php echo write_day('TH', $shop);?></td>
							</tr>
							<tr>
								<th>Fredag</th><td><?php echo write_day('FR', $shop);?></td>
							</tr>
							<tr>
								<th>Lørdag</th><td><?php echo write_day('SA', $shop);?></td>
							</tr>
							<tr>
								<th>Søndag</th><td><?php echo write_day('SU', $shop);?></td>
							</tr>
						</table>
					</div>
					<div class="ws_hidden_form_fields">
	                    <input type='hidden' name='dyn_street_<?php echo $shop->servicePointId; ?>' value="<?php echo $shop->deliveryAddress->streetName." ".$shop->deliveryAddress->streetNumber?>"/>
	                    <input type='hidden' name='dyn_postal_code_<?php echo $shop->servicePointId; ?>' value="<?php echo $shop->deliveryAddress->postalCode ?>"/>
	                    <input type='hidden' name='dyn_country_<?php echo $shop->servicePointId; ?>'/ value="<?php echo $shop->deliveryAddress->countryCode ?>">
	                    <input type='hidden' name='dyn_city_<?php echo $shop->servicePointId; ?>' value="<?php echo $shop->deliveryAddress->city ?>"/>
	                    <input type='hidden' name='dyn_name_<?php echo $shop->servicePointId; ?>' value="<?php echo $shop->name ?>"/>
					</div>
				<?php	
					}	
					$count++;
				}
				?>
				<script>set_selection();</script>



		</td>

</tr>

