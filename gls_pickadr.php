<?php 
        require_once('ParcelShop.php');
       
        
        // Get associated shops
        $api = new ParcelShopDK();
	$shops = $api->getShopNear($street, $postal, 5); 




	function write_day($day, $shop){
		$return = "";
		if (count($shop->OpeningHours->Weekday)==0){ return "Ej tilgængelig"; }

		foreach($shop->OpeningHours->Weekday as $curDay){
			if(strtoupper($curDay->day) == strtoupper($day)){
                            $return .= ($return == "" ? $curDay->openAt->From."-".$curDay->openAt->To : "<br/>".$curDay->openAt->From."-".$curDay->openAt->To);
			}
		}
		return ($return == "" ? "Lukket" : $return);
	}
?>  
        <tr>
            <th colspan="2"> Vælg nærmeste GLS Pakkeshop </th>
        </tr>
        <?php 
        if($shops){
        ?>    
        
        <tr>
		<td colspan="2" > 
			<select name="dynamic_destination" id="dynamic_destination_select" onchange="set_selection()" onload="set_selection()" style="width: auto;">
				<?php foreach($shops as $shop){ ?>
					<option value="<?php echo trim($shop->Number)?>">
						<?php echo $shop->CompanyName ?> - <?php echo $shop->Streetname ?>
						(<?php echo $shop->ZipCode . " " . $shop->CityName ?>)
					</option>
				<?php } ?>
			</select>
			<div id="pickup_info">

			</div>
			<?php
				$count = 1;
				foreach($shops as $shop){
					if(is_array($shop->OpeningHours->Weekday)){
				?>

					<div class="service_point" id="servicepoint_<?php echo trim($shop->Number);?>" <?php echo ($shop != $shops[0] ? "style=\"display: none;\"" : "")?>>
						<h3>Åbningstider for <?php echo $shop->CompanyName ?></h3>
						<table>
							<tr>
								<th>Mandag</th><td><?php echo write_day('Monday', $shop);?></td>
							</tr>
							<tr>
								<th>Tirsdag</th><td><?php echo write_day('Tuesday', $shop);?></td>
							</tr>
							<tr>
								<th>Onsdag</th><td><?php echo write_day('Wednesday', $shop);?></td>
							</tr>
							<tr>
								<th>Torsdag</th><td><?php echo write_day('Thursday', $shop);?></td>
							</tr>
							<tr>
								<th>Fredag</th><td><?php echo write_day('Friday', $shop);?></td>
							</tr>
							<tr>
								<th>Lørdag</th><td><?php echo write_day('Saturday', $shop);?></td>
							</tr>
							<tr>
								<th>Søndag</th><td><?php echo write_day('Sunday', $shop);?></td>
							</tr>
						</table>
					</div>
					<div class="ws_hidden_form_fields">
                                            <input type='hidden' name='dyn_street_<?php echo trim($shop->Number); ?>' value="<?php echo $shop->Streetname ?>"/>
                                            <input type='hidden' name='dyn_postal_code_<?php echo trim($shop->Number); ?>' value="<?php echo $shop->ZipCode ?>"/>
                                            <input type='hidden' name='dyn_country_<?php echo trim($shop->Number); ?>' value="<?php echo $shop->CountryCodeISO3166A2 ?>">
                                            <input type='hidden' name='dyn_city_<?php echo trim($shop->Number); ?>' value="<?php echo $shop->CityName ?>"/>
                                            <input type='hidden' name='dyn_name_<?php echo trim($shop->Number); ?>' value="<?php echo $shop->CompanyName ?>"/>
					</div>
				<?php	
					}	
					$count++;
				}
				?>
				<script>set_selection();</script>



		</td>

</tr>

        <?php }else{ ?>
            	<tr><td colspan="2">Ingen pakkeshops fundet for den indtastede adresse. <br> <input type="button" value="Tjek igen" onclick="update_shipping_methods()"></td></tr>
        <?php } ?>
