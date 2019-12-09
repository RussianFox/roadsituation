<?php

include '../staff/functions.php';

$selsh = array('addition'=>null,'confirm'=>0,'discard'=>0,'geometry'=>null,'source'=>'maps.yandex.ru',''=>'','time'=>'','type'=>'accident','location'=>array('lat'=>0,'lon'=>0));
$elsh = array('_index'=>'yandex','_score'=>1,'_type'=>'_doc','_source'=>$selsh);

$tm="1575545550";
$ZZ=14;

$yandexBox = yandexTile(59.99121729316583,30.40303230285645,59.950970488288505,30.23823738098145,$ZZ);

$coords = coord_quadr('5213104');
$yandexBox = yandexTile($coords['y1'],$coords['x1'],$coords['y2'],$coords['x2'],$ZZ);

var_dump($yandexBox);

$context = stream_context_create(array(
    'http' => array(
        'method' => 'GET',
        'header' => "
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36
        "
    ),
));

for ($XX=$yandexBox['xmin']; $XX<=$yandexBox['xmax']; $XX++) {
    for ($YY=$yandexBox['ymin']; $YY<=$yandexBox['ymax']; $YY++) {
	$url = "https://core-jams-rdr.maps.yandex.net/1.1/tiles?trf&l=trje&lang=ru&x=$XX&y=$YY&z=$ZZ&scale=1&tm=$tm&format=json";
	$file=false;
	$file=@file($url,FALSE,$context);
	if ($file) {
	    $file = json_decode($file[0],TRUE,15);
	    if (array_key_exists('error',$file)) {
		continue;
	    }
	    foreach ($file['data']['features'] as $feature) {
		if ($feature['properties']['eventType']==1) {
		    $lat=$feature['geometry']['coordinates'][0];
		    $lng=$feature['geometry']['coordinates'][1];
		    $id=$feature['properties']['HotspotMetaData']['id'];
		    $urld="https://core-jams-rdr.maps.yandex.net/description?id=".$id."&l=trje&lang=ru_RU&tm=$tm";
		    $filed=@file($urld,FALSE,$context);
		    $filed = json_decode($filed[0],TRUE,15);
		    $name="";
		    if ($filed) {
			$name = $filed['description']." ".$filed['startTime'];
		    }
		    echo "DTP in point $lat,$lng $name \r\n";
		    $el = $elsh;
		    $el['_id']=$id;
		    $el['_source']['location']['lat']=$lat;
		    $el['_source']['location']['lon']=$lng;
		    $el['_source']['text']=$name;
		    $el['_source']['time']=time();
		    var_dump($el);
		}
	    }
	}
    }
}

?>