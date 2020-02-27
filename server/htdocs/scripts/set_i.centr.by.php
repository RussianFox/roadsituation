<?php

include '../staff/functions.php';

function numberFormat($digit, $width) {
    while(strlen($digit) < $width)
          $digit = '0' . $digit;
    return $digit;
};

echo "Start ".date('Y-m-d H:i:s')."\r\n";

$pages=['a','s','o'];

$index="icentreby";

$ids = array();

$url = "http://i.centr.by/inforoads/api/v3/repairs";
echo "Start loading: $url ..... \r\n";
$file=false;
$file=@file_get_contents($url);

if ($file) {
	echo "$url loaded \r\n";
	$file = json_decode($file, true);
	
	foreach ($file as $feature) {
	
	    $object_id=$feature['idRd'];
		
		$type="maintenance";
		$name = "Road repair ".$feature['roadNom']." ".$feature['roadTitle']." (".$feature['kmBegin']."-".$feature['kmEnd']."km) <br>".$feature['dateBegin']." - ".$feature['dateEnd'];
		$link="https://transport.mos.ru/";
		$geometry=$feature['geometry'];
		
	    $coordinates=array();
	    foreach ($geometry['coordinates'] as $coordinate) {
			array_push($coordinates,array('lat'=>$coordinate[0],"lng"=>$coordinate[1]));
	    };
	    $center = center_line($coordinates);

	    update_object_int($object_id,$center['lng'],$center['lat'],$type,$name,null,$link,$index,$geometry);
	    $ids[] = $object_id;
	}
	
	clean_objects($index,'must_not',$ids);
	replace_index_alias($index,"roadsituation_icentreby");
} else {
	echo "Load $url failed \r\n";
}
echo "Finish ".date('Y-m-d H:i:s')."\r\n";
echo "----------------------------------------\r\n";
?>