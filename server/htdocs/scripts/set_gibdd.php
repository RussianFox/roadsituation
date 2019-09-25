<?php

include '../staff/functions.php';

function numberFormat($digit, $width) {
    while(strlen($digit) < $width)
          $digit = '0' . $digit;
    return $digit;
};

$date = new DateTime("now", new DateTimeZone("UTC"));
$index="gibdd_".($date->format('Y-m-d-H-i'));

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
    $link="https://гибдд.рф/milestones/".$matches[1][$i]."/";
    $name=trim(str_replace('&quot;', '"', $matches[2][$i]));
    $lat=1*trim($matches[3][$i]);
    $lng=1*trim($matches[4][$i]);
    //echo "$lat|$lng $name ($link)\n";
    
    add_object_int($lng,$lat,"cam",$name,null,$link,$index);
    
}
} 
echo "Region $ii: $i\n";

}

replace_index_alias($index,"roadsituation_gibdd")

?>