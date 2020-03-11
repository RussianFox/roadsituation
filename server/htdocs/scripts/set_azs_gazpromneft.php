<?php

include '../staff/functions.php';

function numberFormat($digit, $width) {
    while(strlen($digit) < $width)
          $digit = '0' . $digit;
    return $digit;
};

$index="azs_gazpromneft";
$index_alias="roadsituation_$index"; //FALSE if no need
$params = ['index' => $index];
$bool=$client->indices()->exists(['index' => $index]);
if (!$bool) {
	echo "Index is not exist, creating it \r\n";
    $response = $client->indices()->create(['index' => $index]);
}
$query = $client->count(['index' => $index]);
$docsCount_start=1*$query['count'];
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
	
	foreach ($matches[0] as $key => $value) {
	
	    $object_id=1*$matches[1][$key];
	    $type="azs";
	    $name = trim($matches[2][$key])." ".trim($matches[3][$key])." ".trim($matches[4][$key]);
	    
	    $lat=1*trim($matches[6][$key]);
	    $lng=1*trim($matches[5][$key]);
	    
	    $link="https://www.gpnbonus.ru/our_azs/";
	    update_object_int($object_id,$lng,$lat,$type,$name,null,$link,$index);
	    $ids[] = $object_id;
	};
	echo "Loading objects success \r\n";
	
	$query = $client->count(['index' => $index]);
	$docsCount_add=1*$query['count'];
	$docsCount_clean=$docsCount_add;
	if (((count($ids))/$docsCount_start)*100 > 70) {
		clean_objects($index,'must_not',$ids);
		echo "Cleaning success \r\n";
		$query = $client->count(['index' => $index]);
		$docsCount_clean=1*$query['count'];
	} else {
		echo "Cleaning cancelled \r\n";
	};
	
	if ($index_alias) { replace_index_alias($index,$index_alias); };
	echo "Statistics. Docs added: ".($docsCount_add-$docsCount_start)." Docs cleaned: ".($docsCount_add-$docsCount_clean)." Docs now: ".$docsCount_clean."\r\n";
} else {
	echo "failed \r\n";
};

echo "Finish ".date('Y-m-d H:i:s')."\r\n";
echo "----------------------------------------\r\n";
?>