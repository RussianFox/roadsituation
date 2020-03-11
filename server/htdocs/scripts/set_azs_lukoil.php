<?php

include '../staff/functions.php';

function numberFormat($digit, $width) {
    while(strlen($digit) < $width)
          $digit = '0' . $digit;
    return $digit;
};

echo "Start ".date('Y-m-d H:i:s')."\r\n";

$index = "azs_lukoil";
$index_alias="roadsituation_$index"; //FALSE if no need
$params = ['index' => $index];
$bool=$client->indices()->exists($params);
if (!$bool) {
    $response = $client->indices()->create($params);
}
$bool=$client->indices()->exists(['index' => $index]);
if (!$bool) {
	echo "Index is not exist, creating it \r\n";
    $response = $client->indices()->create(['index' => $index]);
}
$query = $client->count(['index' => $index]);
$docsCount_start=1*$query['count'];
$ids=false;

$context = stream_context_create(array(
    'ssl' => array(
	"verify_peer"=>false,
	"verify_peer_name"=>false,
    ),
    'http' => array(
        'method' => 'GET',
        'header' => "
X-Requested-With: XMLHttpRequest
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36
Accept-Encoding: gzip, deflate, br
        "
    ),
));


$url = "https://auto.lukoil.ru/api/cartography/GetSearchObjects?form=gasStation";

$file=false;
$file=@file_get_contents($url,FALSE,$context);
if ($file) {
	$file = json_decode ( $file, TRUE, 15 );
	foreach ($file['GasStations'] as $station) {
	    $id = $station['GasStationId'];
	    $lat=1*trim($station['Latitude']);
    	    $lng=1*trim($station['Longitude']);
    	    $type='azs';
    	    $name = $station['DisplayName']." ".$station['City']." ".$station['Street'];
    	    $link = "https://auto.lukoil.ru/ru/ProductsAndServices/PetrolStation?type=gasStation&id=".$id;
	    update_object_int($id,$lng,$lat,$type,$name,null,$link,$index);
	    $ids[]=$id;
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
	echo "Failed to load ".$url." \r\n";
};

echo "Finish ".date('Y-m-d H:i:s')."\r\n";
echo "----------------------------------------\r\n";
?>