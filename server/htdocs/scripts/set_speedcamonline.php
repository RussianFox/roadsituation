<?php

include "../staff/functions.php";

echo "Start ".date('Y-m-d H:i:s')."\r\n";

$index="speedcamonline";

// Create a stream
$opts = [
    "http" => [
        "method" => "GET",
        "header" => "Accept-language: en\r\n" .
        "User-Agent: Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.102011-10-16 20:23:10\r\n" // i.e. An iPad
    ]
];

$context = stream_context_create($opts);

$files=array();
//$files[] = "Rus";
$files[] = "Ukraine";
$files[] = "LatvijaA";
$files[] = "LatvijaC";
$files[] = "LatvijaR";
$files[] = "GE";
$files[] = "uz";
$files[] = "tm";
$files[] = "kz";
$files[] = "by";
$files[] = "Fin";
$files[] = "EU";
$files[] = "Armenia";

$ids=false;
$needclean=true;

foreach ($files as $file) {
    $ii=0;
    echo "Region: ".$file."\r\n";
    //$url = "https://speedcamonline.ru/primo/$file/nomobile/";
    $url = "https://speedcamonline.ru/primo/$file&filter_id=&cam_name=&cam_adress=&cam_N=&cam_E=&region=-1&kind_list=1000,0,3,12,14,1"
    echo "File ".$url;
    $filec = @file($url,false,$context);
    if ($filec) {
	echo " loaded success\r\n";
        foreach ($filec as $line) {
	    $cam = explode(",", $line);
    	    if (count($cam)==7) {
    		$ii++;
    		$id=(1*trim($cam[0]));
    		$fullid=$file.$id;
		$lng=1*trim($cam[1]);
		$lat=1*trim($cam[2]);
	        $speed=1*trim($cam[4]);
		$direction=1*trim($cam[5]);
		$text="";
		if ( ($speed>0) and ($direction>0) ) {
		    $text="Speed: ".$speed." ".($direction==1?"one way":"in both direction");
		};
		update_object_int($fullid,$lng,$lat,"cam",$text,null,"http://speedcamonline.ru/point/$file/$id",$index);
		$ids[]=$fullid;
	    }
	};
	echo "Objects: $ii\r\n";
    } else {
	echo " loading failed\r\n";
	$needclean=false;
    }
};

if ($needclean) {
    echo "Start cleaning\r\n";
    clean_objects($index,'must_not',$ids);
};

replace_index_alias($index,"roadsituation_speedcamonline");

echo "Finish ".date('Y-m-d H:i:s')."\r\n";
echo "----------------------------------------\r\n";
?>