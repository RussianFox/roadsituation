<?php

include '../staff/functions.php';

echo "Start ".date('Y-m-d H:i:s')."\r\n";

$index="saratov_gov_ru";

$params = ['index' => $index];
$bool=$client->indices()->exists($params);
if (!$bool) {
	echo "Index is not exist, creating it \r\n";
    $response = $client->indices()->create($params);
}

$query = $client->count(['index' => $index]);
$docsCount_start=1*$query['count'];

$ids = array();

$url = "http://dorogi.saratov.gov.ru/modules/LoadObjects.php";
echo "Start loading: $url ..... \r\n";
$file=false;
$file=@file_get_contents($url);

if ($file) {
	echo "$url loaded \r\n";
	$file = json_decode($file, true);
	
	foreach ($file['features'] as $feature) {
	
		$prop = $feature['properties'];
		if ( (!empty($prop['factend'])) and ($prop['factend'] != "01.01.1970") ) { continue; };
		$object_id=$prop['id'];
		$type="maintenance";
		$name = $prop['worktype']." ".$prop['name']." ".$prop['contractor']." тел. ".$prop['phone'].". Плановая дата окончания: ".$prop['planend']." " ;
		$link="http://dorogi.saratov.gov.ru/maprepair.php";
		$geometry = $feature['geometry'];
		$geometry['coordinates']=checkGeometry($geometry['coordinates']);
		
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
			echo "Geometry invalid on $object_id \r\n";
		    continue;
		}

		update_object_int($object_id,$center['lng'],$center['lat'],$type,$name,null,$link,$index,$geometry);
		$ids[] = $object_id;
	}
	
	echo "Loading objects success \r\n";
	
	$query = $client->count(['index' => $index]);
	$docsCount_add=1*$query['count'];
	$docsCount_clean=$docsCount_add;
	
	clean_objects($index,'must_not',$ids);
	echo "Cleaning success \r\n";
	
	$query = $client->count(['index' => $index]);
	$docsCount_clean=1*$query['count'];
	
	replace_index_alias($index,"roadsituation_saratov_gov_ru");
	
	echo "Statistics. Docs added: ".($docsCount_add-$docsCount_start)." Docs cleaned: ".($docsCount_add-$docsCount_clean)." Docs now: ".$docsCount_clean."\r\n";
} else {
	echo "Load $url failed \r\n";
}
echo "Finish ".date('Y-m-d H:i:s')."\r\n";
echo "----------------------------------------\r\n";
?>