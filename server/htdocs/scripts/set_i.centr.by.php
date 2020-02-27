<?php

include '../staff/functions.php';

function numberFormat($digit, $width) {
    while(strlen($digit) < $width)
          $digit = '0' . $digit;
    return $digit;
};

echo "Start ".date('Y-m-d H:i:s')."\r\n";

$index="icentreby";

$ids = array();

$url = "http://i.centr.by/inforoads/api/v3/repairs";
echo "Start loading: $url ..... \r\n";
$file=false;
$file=@file_get_contents($url);

if ($file) {
	echo "$url loaded \r\n";
	$file = str_replace('}"', '}', $file);
	$file = str_replace('"{', '{', $file);
	$file = str_replace('\"', '"', $file);
	$file = json_decode($file, true);
	
	foreach ($file as $feature) {
	
		$object_id=$feature['idRd'];
		$type="maintenance";
		$name = "Road repair ".$feature['roadNom']." ".$feature['roadTitle']." (".$feature['kmBegin']."-".$feature['kmEnd']."km) <br>".$feature['dateBegin']." - ".$feature['dateEnd'];
		$link="http://i.centr.by/inforoads/ru/repairs";
		$geometry=$feature['geometry'];
		
		//echo $object_id." ".$geometry['type']."\r\n";
		
		$coordinates=array();
		If ($geometry['type'] == "Point") {
			$center = array('lat'=>$geometry['coordinates'][1],"lng"=>$geometry['coordinates'][0]);
		} elseif ($geometry['type'] == "LineString") {
			foreach ($geometry['coordinates'] as $coordinate) {
				array_push($coordinates,array('lat'=>$coordinate[1],"lng"=>$coordinate[0]));
			};
			$center = center_line($coordinates);
		} else if ($geometry['type'] == "MultiLineString") {
			foreach ($geometry['coordinates'] as $coords) {
			    foreach ($coords as $coordinate) {
				array_push($coordinates,array('lat'=>$coordinate[1],"lng"=>$coordinate[0]));
			    };
			};
			$center = center_line($coordinates);
		} else {
		    continue;
		}

		update_object_int($object_id,$center['lng'],$center['lat'],$type,$name,null,$link,$index,$geometry);
		$ids[] = $object_id;
	}
	
	echo "Loading objects success \r\n";
	print_r($ids);
	//clean_objects($index,'must_not',$ids);
	//echo "Cleaning success \r\n";
	replace_index_alias($index,"roadsituation_icentreby");
} else {
	echo "Load $url failed \r\n";
}
echo "Finish ".date('Y-m-d H:i:s')."\r\n";
echo "----------------------------------------\r\n";
?>