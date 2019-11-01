<?php

include '../staff/functions.php';

function numberFormat($digit, $width) {
    while(strlen($digit) < $width)
          $digit = '0' . $digit;
    return $digit;
};

echo "Start ".date('Y-m-d H:i:s')."\r\n";

$index = "azs_shell";

$params = ['index' => $index];
$bool=$client->indices()->exists($params);
if (!$bool) {
    $response = $client->indices()->create($params);
}

$context = stream_context_create(array(
    'http' => array(
        'method' => 'GET',
        'header' => "
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36
        "
    ),
));


$url = "https://shellgsl-media.s3.amazonaws.com/prod/poi/GRM/rtl/RU.zip";
$file=@file_get_contents($url);
if (!$file) {
    return;
}
$temp_pointer = tmpfile();
fwrite($temp_pointer, $file);
$temp_path = stream_get_meta_data($temp_pointer)['uri'];
$file = @file(sprintf('zip://%s#%s', $temp_path, "RU.csv"));
fclose($temp_pointer);


    $ids = array();
    if ($file) {
//	$file = json_decode ( $file, TRUE, 15 );
//	var_dump($file);
//	return;
	foreach ($file as $string) {
	    $station = explode(',',$string);
//	    continue;
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
	echo "Failed to load zip archive \r\n";
    }

clean_objects($index,'must_not',$ids);
replace_index_alias($index,"roadsituation_$index");
echo "Finish ".date('Y-m-d H:i:s')."\r\n";
echo "----------------------------------------\r\n";
?>