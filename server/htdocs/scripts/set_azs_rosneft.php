<?php

include '../staff/functions.php';

function numberFormat($digit, $width) {
    while(strlen($digit) < $width)
          $digit = '0' . $digit;
    return $digit;
};

echo "Start ".date('Y-m-d H:i:s')."\r\n";

$index = "azs_rosneft";

$params = ['index' => $index];
$bool=$client->indices()->exists($params);
if (!$bool) {
    $response = $client->indices()->create($params);
}

$context = stream_context_create(array(
    'http' => array(
        'method' => 'POST',
        'header' => "
Accept: application/json, text/javascript, */*; q=0.01
X-Requested-With: XMLHttpRequest
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36
Content-Type: application/x-www-form-urlencoded; charset=UTF-8
Accept-Encoding: gzip, deflate, br
        "
    ),
));


$url = "http://www.rosneft-azs.ru/map_search";



    $ids=false;
    $file=false;
    $file=@file_get_contents($url,null,$context);
    if ($file) {
	$file = json_decode ( $file, TRUE, 15 );
//	var_dump($file['stations']);
//	return;
	foreach ($file['stations'] as $station) {
	    $id = $station['id'];
	    $lat=1*trim($station['lat']);
    	    $lng=1*trim($station['lng']);
    	    $type='azs';
    	    $name = $station['name']." ".$station['address']." ".$station['contacts']." ".$station['email'];
    	    $link = "http://www.rosneft-azs.ru";
	    update_object_int($id,$lng,$lat,$type,$name,null,$link,$index);
	    $ids[]=$id;
	}
    } else {
	echo "Failed to load ".$url." \r\n";
    }

clean_objects($index,'must_not',$ids);
replace_index_alias($index,"roadsituation_$index");
echo "Finish ".date('Y-m-d H:i:s')."\r\n";
echo "----------------------------------------\r\n";
?>