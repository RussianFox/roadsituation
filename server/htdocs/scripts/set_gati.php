<?php

include '../staff/functions.php';

function numberFormat($digit, $width) {
    while(strlen($digit) < $width)
          $digit = '0' . $digit;
    return $digit;
};

$pages=['a','s','o'];

echo "Start ".date('Y-m-d H:i:s')."\r\n";

$index="gati";
$ids=array();

foreach ($pages as $ii) {

    $url = "http://xc.gati-online.ru/mapxml.php?a=".$ii; //aszo
    $matches=false;
    $file=false;
    $file=@file_get_contents($url);
    if ($file) {
        preg_match_all('/GeoObject gml:id="(.*)".*gml:name>(.*)<.*style>(.*)<.*gml:srk>(.*)<.*adr>(.*)<.*adr2>(.*)<.*gml:pos>(.*) (.*)</sUsi',$file, $matches);

	for ($i=0; $i<count($matches[1]); $i++) {
	    $id = $matches[1][$i];
	    $style=ltrim($matches[3][$i], '#');
	    $type="maintenance";
	    if (in_array($style, array('ogr4','ogr1','zakr1','zakr4'))) {
		continue;
	    };
	    if (in_array($style, array('zakr','zakr2','zakr3','zakr5'))) {
		$type="block";
	    }
	    $link="http://xc.gati-online.ru/allinfo.php?key=".$matches[1][$i];
    	    $name=trim(str_replace('&quot;', '"', $matches[2][$i]." ".$matches[5][$i]." ".$matches[6][$i]." ".$matches[4][$i]));
	    $lat=1*trim($matches[8][$i]);
    	    $lng=1*trim($matches[7][$i]);
	    update_object_int($id,$lng,$lat,$type,$name,null,$link,$index);
	    $ids[]=$id;
	}
    } else {
	echo "Failed to load ".$url." \r\n";
    }
}
clean_objects($index,'must_not',$ids);
replace_index_alias($index,"roadsituation_gati");
echo "Finish ".date('Y-m-d H:i:s')."\r\n";
echo "----------------------------------------\r\n";
?>