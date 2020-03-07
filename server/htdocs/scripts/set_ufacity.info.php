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
    $response = $client->indices()->create($params);
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
	foreach ($file['closed']['features'] as $feature) {
	
		$object_id=$feature['id'];
		$type="maintenance";
		$name = "Ремонт дороги, движение перекрыто";
		$link="https://ufacity.info/remontdor/";
		$geometry=$feature['geometry'];
		
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
	
	$query = $client->count(['index' => $index]);
	$docsCount_add=1*$query['count'];
	$docsCount_clean=$docsCount_add;
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