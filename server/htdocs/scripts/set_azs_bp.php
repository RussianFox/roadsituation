<?php

include '../staff/functions.php';

function numberFormat($digit, $width) {
    while(strlen($digit) < $width)
    $digit = '0' . $digit;
    return $digit;
};

echo "Start ".date('Y-m-d H:i:s')."\r\n";

$index = "azs_bp";
$index_alias="roadsituation_$index"; //FALSE if no need
$params = ['index' => $index];
$bool=$client->indices()->exists($params);
if (!$bool) {
    $response = $client->indices()->create($params);
};

$ids = array();
$bool=$client->indices()->exists(['index' => $index]);
if (!$bool) {
	echo "Index is not exist, creating it \r\n";
    $response = $client->indices()->create(['index' => $index]);
}
$query = $client->count(['index' => $index]);
$docsCount_start=1*$query['count'];
$context = stream_context_create(array(
    'http' => array(
        'method' => 'GET',
        'header' => "
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36
        "
    ),
));

#$url = "https://locator.shell.com.ru/deliver_country_csv.csv?footprint=RU&site=cf&launch_country=RU&networks=ALL";
$url="https://bpplusmaps.bp.com/export_viewport/25.csv?swlat=-89.0&swlng=-179.0&nelat=89.0&nelng=179.0&language=ru_RU&filter_list[]=flag_BP&filter_list[]=flag_Restaurant&filter_list[]=flag_AutomaticSite&filter_list[]=flag_Toilet&filter_list[]=flag_Wifi&filter_list[]=flag_Ultimate&filter_list[]=flag_AdBlue&filter_list[]=flag_Shop&filter_list[]=flag_LPG&filter_list[]=flag_Open24Hours&filter_list[]=flag_WildBeanCafe&filter_list[]=flag_CarWash&selection_filter=TRUE";

$file=false;
$file=@file($url,FALSE,$context);

if ($file) {
	echo "File loaded \r\n";
	foreach ($file as $string) {
	    $station = explode('	',$string);
	    if (count($station)<2) {
		continue;
	    }
	    $lat=floatval(preg_replace('/[^0-9\.]/', '', $station[12]));
    	    $lng=floatval(preg_replace('/[^0-9\.]/', '', $station[13]));
    	    if (($lng==0) and ($lat==0)) {
    		continue;
    	    }
    	    $type='azs';
    	    $name = "BP ".$station[2];
    	    $link = "https://bp.com/";
    	    $id=$station[0];
    	    //var_dump($station);
    	    //echo "$lat $lng \r\n";
	    update_object_int(''.$id,$lng,$lat,$type,$name,null,$link,$index);
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
	echo "Failed to load file \r\n";
};

echo "Finish ".date('Y-m-d H:i:s')."\r\n";
echo "----------------------------------------\r\n";
?>