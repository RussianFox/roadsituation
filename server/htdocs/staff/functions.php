<?php

include 'config/main.php';
include 'config/env.php';
include 'config/elasticsearch.php';


$types = ['cam', 'accident', 'block', 'maintenance', 'message', 'other', 'sos'];

use Elasticsearch\ClientBuilder;
require 'vendor/autoload.php';
$client = ClientBuilder::create()->setHosts($eshosts)->build();

function correct_coords($x,$min,$max) {
    $xn = ((($x-$min)%($max-$min))+$max-$min)%($max-$min)+$min;
    return $xn;
}

function convert_coords($x1,$y1,$x2,$y2) {

    $x1=correct_coords($x1,$GLOBALS['xmin'],$GLOBALS['xmax']);
    $x2=correct_coords($x2,$GLOBALS['xmin'],$GLOBALS['xmax']);

    $y1=correct_coords($y1,$GLOBALS['ymin'],$GLOBALS['ymax']);
    $y2=correct_coords($y2,$GLOBALS['ymin'],$GLOBALS['ymax']);

    $xc=$GLOBALS['xmax']-$GLOBALS['xmin'];
    $xq=$xc/$GLOBALS['xstep'];
    $qx1 = floor(min($x1,$x2)/$GLOBALS['xstep'])+1;
    $qx2 = floor(max($x1,$x2)/$GLOBALS['xstep'])+1;
    $qy1 = floor((min($y1,$y2)-$GLOBALS['ymin'])/$GLOBALS['ystep'])+1;
    $qy2 = floor((max($y1,$y2)-$GLOBALS['ymin'])/$GLOBALS['ystep'])+1;

    $arr=[];

    for ($qyn=$qy1; $qyn<=$qy2; $qyn++) {
	for ($qxn=$qx1; $qxn<=$qx2; $qxn++) {
	    $arr[]=((($qyn-1)*$xq))+$qxn;
	}
    };
    return $arr;
}

function coord_quadr($qu) {
    $xc=$GLOBALS['xmax']-$GLOBALS['xmin'];
    $xq=$xc/$GLOBALS['xstep'];
    $tv=floor(($qu-1)/$xq);
    $y1=($tv*$GLOBALS['ystep'])+$GLOBALS['ymin'];
    $y2=$y1+$GLOBALS['ystep'];
    $x2=(($qu-($tv*$xq))*$GLOBALS['xstep'])+$GLOBALS['xmin'];
    $x1=$x2-$GLOBALS['xstep'];
    $arr=['x1' => $x1, 'x2' => $x2, 'y1' => $y1, 'y2' => $y2];
    return $arr;
}

function add_error($text) {
    echo "Error: ".$text;
    exit(1);
}

function add_success($text) {
    echo "Success: ".$text;
    exit(0);
}

function vote_object($index,$id,$vote) {
    global $client;

    $val = 1*$vote;
    if ($val==1) {
        $params = [
	    'index' => $index,
    	    'id'    => $id,
    	    'type' => '_doc',
    	    'body'  => [
    		'script' => [
        	    'source' => 'ctx._source.confirm += params.count',
        	    'params' => [
        		'count' => 1
        	    ],
    		],
    		'upsert' => [
    		    'confirm' => 1
    		]
    	    ]
	];

	$result = $client->update($params);
	return;
    }

    if ($val==-1) {
        $params = [
		'index' => $index,
    	    'id'    => $id,
    	    'type' => '_doc',
    	    'body'  => [
    		'script' => [
        	    'source' => 'ctx._source.discard += params.count',
        	    'params' => [
        		'count' => 1
        	    ],
    		],
    		'upsert' => [
    		    'discard' => 1
    		]
    	    ]
	];

	$result = $client->update($params);
	return;
    }

    
    add_error("Wrong vote");
}

function check_object_type($type) {
    global $object_perm_types,$object_temp_types;
    if (!in_array($type,$object_perm_types)) {
	return 'permament';
    };
    if (!in_array($type,$object_temp_types)) {
	return 'temporary';
    };
    return false;
}

function add_object($lng, $lat, $type="other", $text="", $addition="", $source="user", $indexname=False) {
    global $client;

    $date = new DateTime("now", new DateTimeZone("UTC"));
    $index="roadsituation".($indexname?"_".$indexname:"")."_".($date->format('Y-m-d-H'));

    $index_type = check_object_type($type);
    if (!$index_type) {
	add_error("Wrong type");
    };

    $params = [
	'index' => $index."_".$index_type,
        'body' => [
    	    'time' => time(),
    	    'type' => $type,
	    'location' => [
		'lat' => $lat,
		'lon' => $lng
	    ],
	    'addition' => $addition,
	    'source' => $source,
	    'text' => $text,
	    'confirm' => 0,
	    'discard' => 0
    	]
    ];

    
    $result = $client->index($params);
}

function center_line($coordinates) {

    $lenght=-1;
    foreach($coordinates as $coord) {
	if ($lenght<0) {
    	    $lenght=0;
	} else {
	    $lenght=$lenght+sqrt(pow($coord['lat']-$tcoord['lat'],2)+pow($coord['lng']-$tcoord['lng'],2));
	};
	$tcoord=$coord;
    };
    $tlenght = $lenght/2;
    $lenght=-1;
    foreach($coordinates as $coord) {
        if ($lenght<0) {
            $lenght=0;
        } else {
            $lenght=$lenght+sqrt(pow($coord['lat']-$tcoord['lat'],2)+pow($coord['lng']-$tcoord['lng'],2));
        };
        if ($lenght>$tlenght) {
            $lat=($coord['lat']+$tcoord['lat'])/2;
            $lng=($coord['lng']+$tcoord['lng'])/2;
            return array('lat'=>$lat, 'lng'=>$lng);
        }
        $tcoord=$coord;
    };
    if (count($coordinates)>0) {
	if ($coordinates[0]['lat'] && $coordinates[0]['lng']) {
	    return array('lat'=>1*$coordinates[0]['lat'], 'lng'=>1*$coordinates[0]['lng']);
	}
    }
    return false;
};

function replace_index_alias($index, $alias) {
    global $client;
    
    $indexParams['index'] = $alias;
    if ($client->indices()->exists($indexParams)) {
    
	$params['body'] = array(
    	    'actions' => array(
        	array(
            	    'remove' => array(
                	'alias' => $alias,
                	'index' => '*' 
            	    )
        	)
    	    )
	);

	try {
	    $result = $client->indices()->updateAliases($params);
	} catch (Exception $e)  {
	    return false;
	}

    } 
    $params['body'] = array(
        'actions' => array(
            array(
                'add' => array(
                    'index' => $index,
                    'alias' => $alias
                )
            )
        )
    );

    try {
	$result = $client->indices()->updateAliases($params);
	return true;
    } catch (Exception $e)  {
	return false;
    }
    
}

function clean_objects($index, $type, $range) {
    global $client;

    $index_type = check_object_type($type);
    if (!$index_type) {
	add_error("Wrong type");
    };

    $params['index'] = $index;
    $params['body']['query']['bool'][$type] =
	[
	    'terms'=>[
		'_id'=>$range
	    ]
	];

    try {
        $result = $client->deleteByQuery($params);
	return true;
    } catch (Exception $e)  {
	return false;
    }
}

function add_object_int($lng, $lat, $type="other", $text="", $addition="", $source, $indexname) {
    global $client;

    $date = new DateTime("now", new DateTimeZone("UTC"));
    $index=$indexname;

    $index_type = check_object_type($type);
    if (!$index_type) {
	add_error("Wrong type");
    };

    $params = [
	'index' => $index,
        'body' => [
    	    'time' => time(),
    	    'type' => $type,
	    'location' => [
		'lat' => $lat,
		'lon' => $lng
	    ],
	    'addition' => $addition,
	    'source' => $source,
	    'text' => $text,
	    'confirm' => 0,
	    'discard' => 0
    	]
    ];

    try {
        $result = $client->index($params);
	return true;
    } catch (Exception $e)  {
	return false;
    }
}


function update_object_int($id,$lng, $lat, $type="other", $text="", $addition="", $source, $indexname,$geometry=null) {
    global $client;

    $date = new DateTime("now", new DateTimeZone("UTC"));
    $index=$indexname;

    $index_type = check_object_type($type);
    if (!$index_type) {
	add_error("Wrong type");
    };

    $params = [
    	'index' => $index,
    	'id'    => $id,
    	'body'  => [
    		'doc' => [
			'location' => [
			    'lat' => 1*$lat,
			    'lon' => 1*$lng
			],
			'addition' => $addition,
			'text' => $text
    		],
    		'upsert' => [
		    'time' => time(),
    		    'type' => $type,
		    'location' => [
			'lat' => 1*$lat,
			'lon' => 1*$lng
		    ],
		    'geometry' => $geometry,
		    'addition' => $addition,
		    'source' => $source,
		    'text' => $text,
		    'confirm' => 0,
		    'discard' => 0
    		]
        ]
    ];

    try {
        $result = $client->update($params);
	return true;
    } catch (Exception $e)  {
	return false;
    }
}


function get_quadr($quadr) {
    global $client;
    $start = microtime(true);
    $coords = coord_quadr($quadr);
    
    $items = [];
    $iloaded = 0;
    $iall = 0;
    
    do {
        $params['index'] = 'roadsituation*';
	$params['size'] = 100;
	$params['from'] = $iloaded;
        $params['body']['query']['geo_bounding_box']['location'] =
		[
		    'top_left'=>[
			'lat'=>$coords['y2'],
			'lon'=>$coords['x1']
		    ],
		    'bottom_right'=>[
			'lat'=>$coords['y1'],
			'lon'=>$coords['x2']
		    ]
		];
	$result = $client->search($params);
	$items = array_merge($items,$result['hits']['hits']);
	$iloaded=count($items);
	$iall = $iloaded;
	$iall = 1*$result['hits']['total']['value'];
    } while ($iloaded<$iall);
    $quadrdata=[];
    $quadrdata['quadr']['id']=1*$quadr;
    $quadrdata['quadr']['date']=time();
    $quadrdata['hits']['total']=$result['hits']['total']['value'];
    $quadrdata['items']=$items;
    $quadrdata['quadr']['generate_time']=microtime(true)-$start;
    return $quadrdata;
}

?>