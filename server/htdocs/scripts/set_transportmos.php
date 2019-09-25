<?php

include '../staff/functions.php';

function numberFormat($digit, $width) {
    while(strlen($digit) < $width)
          $digit = '0' . $digit;
    return $digit;
};

$pages=['a','s','o'];

$date = new DateTime("now", new DateTimeZone("UTC"));

$index="transportmos_".($date->format('Y-m-d-H-i'));

    $datar = array('action' => 'get_road_closures_coordinates', 'dt' => $date->format('H:i d.m.Y'));

    $context = stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => "
Pragma: no-cache
Cache-Control: no-cache
Accept: application/json, text/javascript, */*; q=0.01
X-Requested-With: XMLHttpRequest
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36
Sec-Fetch-Mode: cors
Content-Type: application/x-www-form-urlencoded; charset=UTF-8
Sec-Fetch-Site: same-origin
Referer: https://transport.mos.ru/info/closures/?type=&district=&address=&dt=&id=
Accept-Encoding: gzip, deflate, br
Accept-Language: en-US,en;q=0.9,ru;q=0.8
            ",
            'content' => http_build_query($datar),
        ),
    ));

    $url = "https://transport.mos.ru/ajax/transport/";
    print_r($url);
    $matches=false;
    $file=false;
    $file=@file_get_contents($url,null,$context);
    if ($file) {
	echo "URL loaded\n";
	//file_put_contents('transportmos.json', $file);
	$file = json_decode($file, true);
	foreach ($file['json']['features'] as $feature) {
	    $object_id=$feature['options']['object_id'];
	    if (strpos($object_id,'_')) {
		continue;
	    };
	    $type="block";
	    $name=$feature['properties']['hintContent'];
	    $link="https://transport.mos.ru/";
	    $coordinates=array();
	    foreach ($feature['geometry']['coordinates'] as $coordinate) {
		array_push($coordinates,array('lat'=>$coordinate[0],"lng"=>$coordinate[1]));
	    };
	    $center = center_line($coordinates);
	    add_object_int($center['lng'],$center['lat'],$type,$name,null,$link,$index);
	    
	}
	replace_index_alias($index,"roadsituation_transportmos");
    }

?>