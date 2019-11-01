<?php

include '../staff/functions.php';

function numberFormat($digit, $width) {
    while(strlen($digit) < $width)
          $digit = '0' . $digit;
    return $digit;
};

$index="azs_gazpromneft";

$ids = array();

//$datar = array('action' => 'get_road_closures_coordinates', 'dt' => $date->format('H:i d.m.Y'));
$context = stream_context_create(array(
        'http' => array(
            'method' => 'GET',
            'header' => "
Pragma: no-cache
Cache-Control: no-cache
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36
Sec-Fetch-Mode: cors
            ",
//            'content' => http_build_query($datar),
        ),
    ));

    $url = "https://www.gpnbonus.ru/our_azs/?region_id=all";
    echo "Start loading: $url ..... \r\n";
    $matches=false;
    $file=false;
    $file=@file_get_contents($url,null,$context);
    if ($file) {
	echo "loaded \r\n";
	$list = preg_match_all('/id="azs_number_(\d*)">([^<]*)<.*id="azs_address_\d*">([^<]*)<.*serviceText">([^<]*)<.*NewCenterMapC\((.*),(.*),/sUi',$file,$matches);
	
//	var_dump($matches);
//	echo count($matches);
//	return;
	
	foreach ($matches[0] as $key => $value) {
	
	    $object_id=1*$matches[1][$key];
	    $type="azs";
	    $name = trim($matches[2][$key])." ".trim($matches[3][$key])." ".trim($matches[4][$key]);
	    
	    $lat=1*trim($matches[6][$key]);
	    $lng=1*trim($matches[5][$key]);
	    
	    $link="https://www.gpnbonus.ru/our_azs/";
	    update_object_int($object_id,$lng,$lat,$type,$name,null,$link,$index);
	    $ids[] = $object_id;
	}
	
	clean_objects($index,'must_not',$ids);
	replace_index_alias($index,"roadsituation_$index");
    } else {
	echo "failed \r\n";
    }
echo "Finish ".date('Y-m-d H:i:s')."\r\n";
echo "----------------------------------------\r\n";
?>