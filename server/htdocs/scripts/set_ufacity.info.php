<?php

include '../staff/functions.php';

function numberFormat($digit, $width) {
    while(strlen($digit) < $width)
          $digit = '0' . $digit;
    return $digit;
};

echo "Start ".date('Y-m-d H:i:s')."\r\n";

$index="ufacity";
$bool=$client->indices()->exists(['index' => $index]);
if (!$bool) {
	echo "Index is not exist, creating it \r\n";
    $response = $client->indices()->create(['index' => $index]);
}
$query = $client->count(['index' => $index]);
$docsCount_start=1*$query['count'];
$ids = array();

$url = "https://ufacity.info/remontdor/api/data/";
echo "Start loading: $url ..... \r\n";
$file=false;
$file=@file_get_contents($url);

if ($file) {
	echo "$url loaded \r\n";
	$file = json_decode($file, true);
	
	foreach ($file['limit']['features'] as $feature) {
	
		$object_id=$feature['id'];
		$type="maintenance";
		$name = "Ремонт дороги, движение ограничено";
		$link="https://ufacity.info/remontdor/";
		
		$geometry=$feature['geometry'];
		$geometry['coordinates']=checkGeometry($geometry['coordinates']);
		$geometry['coordinates']=rollCoordinates($geometry['coordinates']);
		$center = get_coord_center($geometry);
		if (!$center) {
		    continue;
		};
		
		update_object_int($object_id,$center['lng'],$center['lat'],$type,$name,null,$link,$index,$geometry);
		$ids[] = $object_id;
	}
	foreach ($file['closed']['features'] as $feature) {
	
		$object_id=$feature['id'];
		$type="block";
		$name = "Ремонт дороги, движение перекрыто";
		$link="https://ufacity.info/remontdor/";

		$geometry=$feature['geometry'];
		$geometry['coordinates']=checkGeometry($geometry['coordinates']);
		$geometry['coordinates']=rollCoordinates($geometry['coordinates']);
		$center = get_coord_center($geometry);
		if (!$center) {
		    continue;
		};

		update_object_int($object_id,$center['lng'],$center['lat'],$type,$name,null,$link,$index,$geometry);
		$ids[] = $object_id;
	}
	
	echo "Loading objects success \r\n";
	
	$query = $client->count(['index' => $index]);
	$docsCount_add=1*$query['count'];
	//clean_objects($index,'must_not',$ids);
	//echo "Cleaning success \r\n";
	replace_index_alias($index,"roadsituation_ufacity");
	$query = $client->count(['index' => $index]);
	$docsCount_clean=1*$query['count'];
	echo "Statistics. Docs added: ".($docsCount_add-$docsCount_start)." Docs cleaned: ".($docsCount_add-$docsCount_clean)." Docs now: ".$docsCount_clean."\r\n";
} else {
	echo "Load $url failed \r\n";
}
echo "Finish ".date('Y-m-d H:i:s')."\r\n";
echo "----------------------------------------\r\n";
?>