<?php

include '../staff/functions.php';

function numberFormat($digit, $width) {
    while(strlen($digit) < $width)
          $digit = '0' . $digit;
    return $digit;
};

$index="gibdd";

$params = ['index' => $index];
$bool=$client->indices()->exists($params);
if (!$bool) {
    $response = $client->indices()->create($params);
}

$ids=array();
for ($ii=1;$ii<100;$ii++) {
    $reg=$ii;
    $url = "https://xn--90adear.xn--p1ai/r/".numberFormat($ii, 2)."/milestones/";
    // echo $url

    $i=0;
    $matches=false;
    $file=false;
    $file=@file_get_contents($url);
    if ($file) {
	preg_match_all('/balloonContentBody.*\/milestones\/(.*)\/.*balloonContentHeader: "(.*)".*coordinates:.*\[(.*), (.*)\]/sUsi',$file, $matches);
        //print_r($matches);

	for ($i=0; $i<count($matches[1]); $i++) {
	    $id = $matches[1][$i];
	    $link="https://гибдд.рф/milestones/".$matches[1][$i]."/";
	    $name=trim(str_replace('&quot;', '"', $matches[2][$i]));
	    $lat=1*trim($matches[3][$i]);
	    $lng=1*trim($matches[4][$i]);
	    //echo "$lat|$lng $name ($link)\n";
	    //echo "$lng\n";
	    if (!update_object_int($id,$lng,$lat,"cam",$name,null,$link,$index)) {
		echo "Failed: $lat|$lng $name ($link)\n";
	    };
	    $ids[]=$id;
	}
    }
    echo "Region $ii: $i\n";
}

clean_objects($index,'must_not',$ids);

replace_index_alias($index,"roadsituation_gibdd")

?>