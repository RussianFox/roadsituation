<?php

include 'config/main.php';
include 'config/env.php';
include 'config/elasticsearch.php';

require_once('yandex.class.php');

use Elasticsearch\ClientBuilder;
require 'vendor/autoload.php';
$client = ClientBuilder::create()->setHosts($eshosts)->build();

function yandexTile($lng1,$lat1,$lng2,$lat2,$zoom) {
    $yandex = new Point(0,0);
    $tile1 = $yandex->fromGeoPoint ($lng1, $lat1, $zoom);
    $tile2 = $yandex->fromGeoPoint ($lng2, $lat2, $zoom);
    
    return array(
		'xmax'=>max(intdiv($tile1->x,256),intdiv($tile2->x,256)),
		'xmin'=>min(intdiv($tile1->x,256),intdiv($tile2->x,256)),
		'ymax'=>max(intdiv($tile1->y,256),intdiv($tile2->y,256)),
		'ymin'=>min(intdiv($tile1->y,256),intdiv($tile2->y,256))
	);
}

function correct_coords($x,$min,$max) {
    $xn = ((($x-$min)%($max-$min))+$max-$min)%($max-$min)+$min;
    return $xn;
}

function get_coord_center($geometry) {
	$coordinates=array();
	If ($geometry['type'] == "Point") {
		$center = array('lat'=>$geometry['coordinates'][1],"lng"=>$geometry['coordinates'][0]);
	} elseif ($geometry['type'] == "LineString") {
		foreach ($geometry['coordinates'] as $coordinate) {
			array_push($coordinates,array('lat'=>$coordinate[1],"lng"=>$coordinate[0]));
		};
		$center = center_line($coordinates);
	} else if ($geometry['type'] == "MultiLineString") {
		foreach ($geometry['coordinates'] as $coords) {
			foreach ($coords as $coordinate) {
			array_push($coordinates,array('lat'=>$coordinate[1],"lng"=>$coordinate[0]));
			};
		};
		$center = center_line($coordinates);
	} else {
		$center = false;
	}
	return $center;
}

function checkGeometry($geometry) {
	if (is_array($geometry)) {
		if (count($geometry)==2) {
			if ((array_key_exists(0,$geometry)) and (array_key_exists(1,$geometry))) {
				if ((is_numeric($geometry[0])) and (is_numeric($geometry[1]))) {
					$geometry[0]=1*$geometry[0];
					$geometry[1]=1*$geometry[1];
					return $geometry;
				}
			}
		};
		foreach ($geometry as &$geom) {
			$geom = checkGeometry($geom);
		}
		return $geometry;
	} else {
		return false;
	}
}

function rollCoordinates($geometry) {
	if (is_array($geometry)) {
		if (count($geometry)==2) {
			if ((array_key_exists(0,$geometry)) and (array_key_exists(1,$geometry))) {
				if ((is_numeric($geometry[0])) and (is_numeric($geometry[1]))) {
					$cn=$geometry[0];
					$geometry[0]=$geometry[1];
					$geometry[1]=$cn;
					return $geometry;
				}
			}
		};
		foreach ($geometry as &$geom) {
			$geom = rollCoordinates($geom);
		}
		return $geometry;
	} else {
		return false;
	}
}

function point_in_coords($point,$coords) {

    if (( $point['lon']<min($coords['x1'],$coords['x2']) ) or ( $point['lon']>max($coords['x1'],$coords['x2']) ))  {
		return FALSE;
    };
    
    if (( $point['lat']<min($coords['y1'],$coords['y2']) ) or ( $point['lat']>max($coords['y1'],$coords['y2']) ))  {
		return FALSE;
    };
    
    return TRUE;

}

function parse_yandex($yfile,$coords) {
	
	$arr = array();
	
	$file = json_decode($yfile,TRUE,15);
	if (array_key_exists('error',$file)) {
		return false;
	};
	
	$selsh = array('addition'=>null,'confirm'=>0,'discard'=>0,'geometry'=>null,'source'=>'maps.yandex.ru','time'=>'','type'=>'accident','location'=>array('lat'=>0,'lon'=>0));
	$elsh = array('_index'=>'yandex','_score'=>1,'_type'=>'_doc','_source'=>$selsh);
	
	foreach ($file['data']['features'] as $feature) {
		if ($feature['properties']['eventType']==1) {
			$lat=$feature['geometry']['coordinates'][0];
			$lon=$feature['geometry']['coordinates'][1];
			
			if (!point_in_coords(array('lat'=>$lat,'lon'=>$lon),$coords)) {
				continue;
			};
			
			$id=$feature['properties']['HotspotMetaData']['id'];
			$name="";
			
			//get name
			$urld="https://core-jams-rdr.maps.yandex.net/description?id=".$id."&l=trje&lang=ru_RU&tm=$tm";
			$context = yandex_context();
			$filed=@file($urld,FALSE,$context);
			$filed = json_decode($filed[0],TRUE,15);
			if ($filed) {
				$name = $filed['description']." ".$filed['startTime'];
			};
			
			//echo "DTP in point $lat,$lng $name \r\n";
			
			$el = $elsh;
			$el['_id']=$id;
			$el['_source']['location']['lat']=$lat;
			$el['_source']['location']['lon']=$lon;
			$el['_source']['text']=$name;
			$el['_source']['time']=time();
			$el['_source']['layer']=$url;
			//var_dump($el);
			$arr[]=$el;
		}
	}
	return $arr;
}

function yandex_context() {
    $context = stream_context_create(array(
		'http' => array
			(
				'method' => 'GET',
				'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36"
			),
    ));
	return $context;
}

function yandex_quadr_turbo($coords) {
    $arr=array();

    $selsh = array('addition'=>null,'confirm'=>0,'discard'=>0,'geometry'=>null,'source'=>'maps.yandex.ru','time'=>'','type'=>'accident','location'=>array('lat'=>0,'lon'=>0));
    $elsh = array('_index'=>'yandex','_score'=>1,'_type'=>'_doc','_source'=>$selsh);

    $tm=time();
    $ZZ=13;

    $yandexBox = yandexTile($coords['x1'],$coords['y1'],$coords['x2'],$coords['y2'],$ZZ);

    $context = yandex_context();

	$curl_arr = array();
	$master = curl_multi_init();

    for ($XX=$yandexBox['xmin']; $XX<=$yandexBox['xmax']; $XX++) {
		for ($YY=$yandexBox['ymin']; $YY<=$yandexBox['ymax']; $YY++) {
			$url = "https://core-jams-rdr.maps.yandex.net/1.1/tiles?trf&l=trje&lang=ru&x=$XX&y=$YY&z=$ZZ&scale=1&tm=$tm&format=json";
			$curl_arr[] = curl_init($url);
			curl_setopt(end($curl_arr), CURLOPT_RETURNTRANSFER, true);
			curl_setopt(end($curl_arr), CURLOPT_USERAGENT, "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36");
			curl_multi_add_handle($master, end($curl_arr));
		}
	}
		
	do {
		curl_multi_exec($master,$running);
	} while($running > 0);


	foreach($curl_arr as $cnode)
	{
		$results = curl_multi_getcontent  ( $cnode );
		if ($results) {
		    //var_dump($results);
		}
		$narr = parse_yandex($results,$coords);
		if ($narr) {
			$arr = array_merge($arr, $narr);
		};
	}
	return $arr;
	
	
}

function yandex_quadr($coords) {
    $arr=array();

    $selsh = array('addition'=>null,'confirm'=>0,'discard'=>0,'geometry'=>null,'source'=>'maps.yandex.ru','time'=>'','type'=>'accident','location'=>array('lat'=>0,'lon'=>0));
    $elsh = array('_index'=>'yandex','_score'=>1,'_type'=>'_doc','_source'=>$selsh);

    $tm=time();
    $ZZ=13;

    $yandexBox = yandexTile($coords['x1'],$coords['y1'],$coords['x2'],$coords['y2'],$ZZ);

    $context = stream_context_create(array(
	'http' => array(
    	    'method' => 'GET',
    	    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36"
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
                        $lon=$feature['geometry']['coordinates'][1];
                        
                        if (!point_in_coords(array('lat'=>$lat,'lon'=>$lon),$coords)) {
                    	    continue;
                        };
                        
                        $id=$feature['properties']['HotspotMetaData']['id'];
                        $name="";
                        
                        //get name
                        $urld="https://core-jams-rdr.maps.yandex.net/description?id=".$id."&l=trje&lang=ru_RU&tm=$tm";
                        $filed=@file($urld,FALSE,$context);
                        $filed = json_decode($filed[0],TRUE,15);
                        if ($filed) {
                            $name = $filed['description']." ".$filed['startTime'];
                        };
                        
                        //echo "DTP in point $lat,$lng $name \r\n";
                        
                        $el = $elsh;
                        $el['_id']=$id;
                        $el['_source']['location']['lat']=$lat;
                        $el['_source']['location']['lon']=$lon;
                        $el['_source']['text']=$name;
                        $el['_source']['time']=time();
                        $el['_source']['layer']=$url;
                        //var_dump($el);
                        $arr[]=$el;
                    }
                }
            }
	}
    }
    return $arr;
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

function coord_quadr_err($qu) {
    $xc = floor($qu/(($GLOBALS['xmax']-$GLOBALS['xmin'])/$GLOBALS['xstep']));
    $y1 = ($GLOBALS['ystep']*$xc)+$GLOBALS['ymin'];
    $y2 = $GLOBALS['ystep']+$y1;
    $x1 = ($qu-($xc*($GLOBALS['xmax']-$GLOBALS['xmin'])/$GLOBALS['xstep']))*$GLOBALS['xstep'];
    $x2 = $GLOBALS['xstep']+$x1;
    $arr=['x1' => $x1, 'x2' => $x2, 'y1' => $y1, 'y2' => $y2];
    return $arr;
}

function coord_quadr($qu) {
    $xc=$GLOBALS['xmax']-$GLOBALS['xmin'];
    $xq=$xc/$GLOBALS['xstep'];
	$tv=floor(($qu)/$xq);
	$y1=($tv*$GLOBALS['ystep'])+$GLOBALS['ymin'];
    $y2=$y1+$GLOBALS['ystep'];
	$x1 = (($qu-($xq*$tv))*$GLOBALS['xstep'])+$GLOBALS['xmin'];
	$x2 = $x1+$GLOBALS['xstep'];
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

		try {
			$result = $client->update($params);
			return true;
		} catch (Exception $e)  {
			return false;
		}
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
		
		try {
			$result = $client->update($params);
			return true;
		} catch (Exception $e)  {
			return false;
		}
		
    }
    add_error("Wrong vote");
}

function check_object_type($type) {
    global $object_perm_types,$object_temp_types;
    if (in_array($type,$object_perm_types)) {
		return 'permanent';
    };
    if (in_array($type,$object_temp_types)) {
		return 'temporary';
    };
    return false;
}

function add_object($lng, $lat, $type="other", $text="", $addition="", $source="user", $aid='') {
    global $client;

    $index_type = check_object_type($type);
    if (!$index_type) {
		add_error("Wrong type");
    };

	if (!$aid) {
		$aid='';
	}

    $date = new DateTime("now", new DateTimeZone("UTC"));
    $index="roadsituation_".$index_type."_".($index_type=='temporary'?$date->format('Y-m-d-H-i'):$date->format('Y-m-d'));

	$geometry = array('type'=>"Point","coordinates"=>Array(1*$lng,1*$lat));

    $params = [
	'index' => $index,
        'body' => [
    	    'time' => time(),
    	    'type' => $type,
			'location' => [
				'lat' => $lat,
				'lon' => $lng
			],
			'geometry' => $geometry,
			'aid' => $aid,
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

    if (!in_array($type,['must','must_not'])) {
		add_error("Wrong type");
	}

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

	$geometry = array('type'=>"Point","coordinates"=>Array($lng,$lat));

    $params = [
	'index' => $index,
        'body' => [
    	    'time' => time(),
    	    'type' => $type,
			'location' => [
				'lat' => $lat,
				'lon' => $lng
			],
			'geometry' => $geometry,
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

function remove_object($index,$id,$aid) {
	global $client;
		
	$params = [
    	'index' => $index
    ];

	$params['body']['query']['bool']['must'] =
	[
		[
			"match" => [
				"_id" => $id
			]
		],
		[
			"match" => [
				"aid" => $aid,
			]
		]
	];

	$params['body']['script'] =
	[
        'inline' => 'ctx._source.delete = "true"'
	];

    try {
        $result = $client->updateByQuery($params);
		return true;
    } catch (Exception $e)  {
		var_dump($e);
		return false;
    }
}

function set_stats($id,$obj_count,$generate_time) {
    global $client;

    $index="stats_quadrs_roadsituation";
	
    //$response = $client->indices()->create(['index' => $index]);
	
    $params = [
    	'index' => $index,
    	'id'    => $id,
    	'body'  => [
		'script' => [
		    'source' => 'ctx._source.generate_time_all += params.generate_time; ctx._source.generate_time = params.generate_time; ctx._source.create_count += params.count; ctx._source.obj_count = params.obj_count; ctx._source.obj_count_all += params.obj_count; ctx._source.create_date = params.timenow;',
		    'params' => [
			'generate_time' => $generate_time,
			'obj_count' => $obj_count,
			'count' => 1,
			'timenow' => time()
		    ],
		],
    		'upsert' => [
		    'obj_count' => $obj_count,
		    'obj_count_all' => $obj_count,
    		    'generate_time' => $generate_time,
		    'generate_time_all' => $generate_time,
		    'create_count' => 1,
		    'create_date' => time()
    		]
        ]
    ];

    try {
        $result = $client->update($params);
	return true;
    } catch (Exception $e)  {
	var_dump($e);
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
	
	if (!$geometry) {
		$geometry = array('type'=>"Point","coordinates"=>Array($lng,$lat));
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
				'geometry' => $geometry,
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

function clean_aid($items) {
	foreach($items as &$item) {
		if (isset($item['_source']['aid'])) {
			if ( ($item['_source']['aid']!='') and ($item['_source']['aid']!='!') ) {
				$item['_source']['aid']='!';
			} else {
				unset($item['_source']['aid']);
			}
		}
	};
	return $items;
};

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
        $params['body']['query']['bool']['must']['geo_shape']['geometry'] =
		[
		    'shape'=>[
				'type'=>'envelope',
				'coordinates'=>[[$coords['x1'],$coords['y2']],[$coords['x2'],$coords['y1']]]
		    ],
		    'relation'=>"within"
		];
		
        $params['body']['query']['bool']['must_not']['term'] =
		[
		    "delete" => "true"
		];
		
		$result = $client->search($params);
		$items = array_merge($items,$result['hits']['hits']);
		$iloaded=count($items);
		$iall = 1*$result['hits']['total']['value'];
    } while ($iloaded<$iall);
	
    $quadrdata=[];
    $quadrdata['quadr']['id']=1*$quadr;
    $quadrdata['quadr']['date']=time();
    $quadrdata['quadr']['human_date']=date('D M j G:i:s T Y',$quadrdata['quadr']['date']);
    $quadrdata['hits']['elastic']=$result['hits']['total']['value'];
	$items = clean_aid($items);
    $yandex_items = yandex_quadr_turbo($coords);
    $quadrdata['hits']['yandex']=count($yandex_items);
    $quadrdata['items']=array_merge($items,$yandex_items);
	$quadrdata['hits']['total']=count($quadrdata['items']);
    $quadrdata['quadr']['generate_time']=microtime(true)-$start;
    set_stats(1*$quadr,$quadrdata['hits']['total'],$quadrdata['quadr']['generate_time']);
    return $quadrdata;
}

?>
