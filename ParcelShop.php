<?php
/*
 * Class developed by Webshipr ApS. It may only be used by webshipr, and is under the Webshipr Copyright license.
 */


class ParcelShopDK{

   private $soapclient;

   function __construct($wsdl = "http://www.gls.dk/webservices_v2/wsPakkeshop.asmx?WSDL"){
       $this->soapclient = new SoapClient($wsdl);
   }

   // Get a shop near the address
   public function getShopNear($street, $zip, $amount){
        $result = false;
        $attempts = 0;
        $query_street = explode(' ', preg_replace('/[^A-Za-z0-9\-\ ]/', '', $street));
        while(!$result){
            if($attempts > 0) {
                array_pop($query_street);
            }
            if(count($query_street) == 0){
                return false;
            }
            $tmp_result = $this->queryAddress(implode(' ', $query_street), $zip, $amount);
            if($tmp_result){
                    $result = $tmp_result;
            }
            $attempts++;
        }
        return $result;
   }


   // Try and query
   private function queryAddress($street, $zip, $amount){
       try{
          $result = $this->soapclient->GetNearstParcelShops(array("street" => $street,
              "zipcode" => $zip, "Amount" => $amount ))->GetNearstParcelShopsResult->parcelshops->PakkeshopData;
       } catch (Exception $ex) {
          $result = false;
       }
       return $result;
   }

}
?>