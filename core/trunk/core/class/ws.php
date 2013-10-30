<?php 
global $SOAP_dispatch_map;
global $XMLRPC_dispatch_map;
global $SOAP_typedef;

//test
/*$SOAP_dispatch_map['testMethod'] = array(
                                        'in'  => array('myVar' => 'string'),
                                        'out' => array('out' => 'string'),
                                        'method' => "core#docserver_locations::testMethod"
                                        );*/

// COMMON
$SOAP_typedef['returnArray'] = array(   'status'=>'string',
                                        'value'=>'string',
                                        'error'=>'string'
                                    );
/**************************************************************************************************/
// DOCSERVERS
$SOAP_typedef['docservers'] = array(    'docserver_id'=>'string',
                                        'docserver_type_id'=>'string',
                                        'device_label'=>'string',
                                        'is_readonly'=>'string',
                                        'size_limit_number'=>'string',
                                        'path_template'=>'string',
                                        'coll_id'=>'string',
                                        'priority_number'=>'string',
                                        'docserver_location_id'=>'string',
                                        'adr_priority_number'=>'string'
                                    );
$SOAP_typedef['returnViewResource'] = array('status'=>'string',
                                            'mime_type'=>'string',
                                            'ext'=>'string',
                                            'file_content'=>'string',
                                            'tmp_path'=>'string',
                                            'file_path'=>'string',
                                            'called_by_ws'=>'boolean',
                                            'error'=>'string'
                                    );
$SOAP_dispatch_map['docserverSave'] = array(
                                        'in'  => array('docserver' => '{urn:MySoapServer}docservers', 'mode' => 'string'),
                                        'out' => array('out' => '{urn:MySoapServer}returnArray'),
                                        'method' => "core#docservers::save"
                                    );
$SOAP_dispatch_map['docserverDelete'] = array(
                                        'in'  => array('docserver' => '{urn:MySoapServer}docservers'),
                                        'out' => array('out' => '{urn:MySoapServer}returnArray'),
                                        'method' => "core#docservers::delete"
                                    );
$SOAP_dispatch_map['docserverEnable'] = array(
                                        'in'  => array('docserver' => '{urn:MySoapServer}docservers'),
                                        'out' => array('out' => '{urn:MySoapServer}returnArray'),
                                        'method' => "core#docservers::enable"
                                    );                                    
$SOAP_dispatch_map['docserverDisable'] = array(
                                        'in'  => array('docserver' => '{urn:MySoapServer}docservers'),
                                        'out' => array('out' => '{urn:MySoapServer}returnArray'),
                                        'method' => "core#docservers::disable"
                                    );
$SOAP_dispatch_map['docserverGet'] = array(
                                        'in'  => array('docserverId' => 'string'),
                                        'out' => array('out' => '{urn:MySoapServer}docservers'),
                                        'method' => "core#docservers::getWs"
                                    );
$SOAP_dispatch_map['viewResource'] = array(
                                        'in'  => Array('gedId' => 'int', 'tableName' => 'string', 'adrTableName' => 'string', 'calledByWS' => 'boolean'),
                                        'out' => Array('out' => '{urn:MySoapServer}returnViewResource'),
                                        'method' => "core#docservers::viewResource"
                                    );
/**************************************************************************************************/
// DOCSERVERS LOCATIONS
$SOAP_typedef['docserverLocations'] = array(    'docserver_location_id'=>'string',
                                                'ipv4'=>'string',
                                                'ipv6'=>'string',
                                                'net_domain'=>'string',
                                                'mask'=>'string',
                                                'net_link'=>'string'
                                            );
$SOAP_dispatch_map['docserverLocationSave'] = array(
                                        'in'  => array('docserver_location' => '{urn:MySoapServer}docserverLocations', 'mode' => 'string'),
                                        'out' => array('out' => '{urn:MySoapServer}returnArray'),
                                        'method' => "core#docserver_locations::save"
                                        );
$SOAP_dispatch_map['docserverLocationDelete'] = array(
                                        'in'  => array('docserver_location' => '{urn:MySoapServer}docserverLocations'),
                                        'out' => array('out' => '{urn:MySoapServer}returnArray'),
                                        'method' => "core#docserver_locations::delete"
                                    );
$SOAP_dispatch_map['docserverLocationEnable'] = array(
                                        'in'  => array('docserver_location' => '{urn:MySoapServer}docserverLocations'),
                                        'out' => array('out' => '{urn:MySoapServer}returnArray'),
                                        'method' => "core#docserver_locations::enable"
                                    );
$SOAP_dispatch_map['docserverLocationDisable'] = array(
                                        'in'  => array('docserver_location' => '{urn:MySoapServer}docserverLocations'),
                                        'out' => array('out' => '{urn:MySoapServer}returnArray'),
                                        'method' => "core#docserver_locations::disable"
                                    );
$SOAP_dispatch_map['docserverLocationGet'] = array(
                                        'in'  => array('docserverLocationId' => 'string'),
                                        'out' => array('out' => '{urn:MySoapServer}docserverLocations'),
                                        'method' => "core#docserver_locations::getWs"
                                    );
/**************************************************************************************************/
// DOCSERVERS TYPES
$SOAP_typedef['docserverTypes'] = array(    'docserver_type_id'=>'string',
                                            'docserver_type_label'=>'string',
                                            'is_container'=>'string',
                                            'container_max_number'=>'int',
                                            'is_compressed'=>'string',
                                            'compression_mode'=>'string',
                                            'is_meta'=>'string',
                                            'meta_template'=>'string',
                                            'is_logged'=>'string',
                                            'log_template'=>'string',
                                            'is_signed'=>'string',
                                            'fingerprint_mode'=>'string'
                                            );
$SOAP_dispatch_map['docserverTypeSave'] = array(
                                        'in'  => array('docserver_type' => '{urn:MySoapServer}docserverTypes', 'mode' => 'string'),
                                        'out' => array('out' => '{urn:MySoapServer}returnArray'),
                                        'method' => "core#docserver_types::save"
                                        );
$SOAP_dispatch_map['docserverTypeDelete'] = array(
                                        'in'  => array('docserver_type' => '{urn:MySoapServer}docserverTypes'),
                                        'out' => array('out' => '{urn:MySoapServer}returnArray'),
                                        'method' => "core#docserver_types::delete"
                                    );
$SOAP_dispatch_map['docserverTypeEnable'] = array(
                                        'in'  => array('docserver_type' => '{urn:MySoapServer}docserverTypes'),
                                        'out' => array('out' => '{urn:MySoapServer}returnArray'),
                                        'method' => "core#docserver_types::enable"
                                    );
$SOAP_dispatch_map['docserverTypeDisable'] = array(
                                        'in'  => array('docserver_type' => '{urn:MySoapServer}docserverTypes'),
                                        'out' => array('out' => '{urn:MySoapServer}returnArray'),
                                        'method' => "core#docserver_types::disable"
                                    );
$SOAP_dispatch_map['docserverTypeGet'] = array(
                                        'in'  => array('docserverTypeId' => 'string'),
                                        'out' => array('out' => '{urn:MySoapServer}docserverTypes'),
                                        'method' => "core#docserver_types::getWs"
                                    );
/**************************************************************************************************/
// USERS
$SOAP_typedef['users'] = array(    'user_id'=>'string',
                                'password'=>'string',
                                'firstname'=>'string',
                                'lastname'=>'string',
                                'phone'=>'string',
                                'mail'=>'string',
                                'loginmode'=>'string'
                                );
$SOAP_dispatch_map['userSave'] = array(
                                        'in'  => array('user' => '{urn:MySoapServer}users', 'mode' => 'string'),
                                        'out' => array('out' => '{urn:MySoapServer}returnArray'),
                                        'method' => "core#users::save"
                                        );
$SOAP_dispatch_map['userDelete'] = array(
                                        'in'  => array('user' => '{urn:MySoapServer}users'),
                                        'out' => array('out' => '{urn:MySoapServer}returnArray'),
                                        'method' => "core#users::delete"
                                    );
$SOAP_dispatch_map['userEnable'] = array(
                                        'in'  => array('user' => '{urn:MySoapServer}users'),
                                        'out' => array('out' => '{urn:MySoapServer}returnArray'),
                                        'method' => "core#users::enable"
                                    );
$SOAP_dispatch_map['userDisable'] = array(
                                        'in'  => array('user' => '{urn:MySoapServer}users'),
                                        'out' => array('out' => '{urn:MySoapServer}returnArray'),
                                        'method' => "core#users::disable"
                                    );
$SOAP_dispatch_map['userGet'] = array(
                                        'in'  => array('user' => 'string'),
                                        'out' => array('out' => '{urn:MySoapServer}users'),
                                        'method' => "core#users::getWs"
                                    );

#####################################
## Web Service de versement de données issue du gros scanner
#####################################
$SOAP_typedef['arrayOfData'] = array(
    array(
        'arrayOfDataContent' => '{urn:MySoapServer}arrayOfDataContent'
    )
);

$SOAP_typedef['arrayOfDataContent'] = array(
    'column' => 'string',
    'value' => 'string',
    'type' => 'string',
);

$SOAP_typedef['returnResArray'] = array(
    'returnCode'=> 'int',
    'resId' => 'string',
    'error' => 'string'
);

$SOAP_dispatch_map['storeResource'] = array(
    'in'  => array(
        'encodedFile' => 'string',
        'data' => '{urn:MySoapServer}arrayOfData',
        'collId' => 'string',
        'table' => 'string',
        'fileFormat' => 'string',
        'status' => 'string',
    ),
    'out' => array('out' => '{urn:MySoapServer}returnResArray'),
    'method' => "core#resources::storeResource",
);

$SOAP_dispatch_map['storeExtResource'] = array(
    'in'  => array(
        'resId' => 'long',
        'data' => '{urn:MySoapServer}arrayOfData',
        'table' => 'string',
    ),
    'out' => array('out' => '{urn:MySoapServer}returnResArray'),
    'method' => "core#resources::storeExtResource",
);

$SOAP_dispatch_map['storeResourceFromURI'] = array(
    'in'  => array(
        'fileURI' => 'string',
        'data' => '{urn:MySoapServer}arrayOfData',
        'collId' => 'string',
        'table' => 'string',
        'fileFormat' => 'string',
        'status' => 'string',
    ),
    'out' => array('out' => '{urn:MySoapServer}returnResArray'),
    'method' => "core#resources::storeResourceFromURI",
);

$SOAP_typedef['searchParams'] = array(
    'country' => 'string',
    'docDate' => 'date',
);

$SOAP_typedef['listOfResources'] = array(
    'resid' => 'long',
    'identifier' => 'string',
    'contactName' => 'string',
    'country' => 'int',
    'amount' => 'string',
    'customer' => 'string',
    'docDate' => 'string',
);

$SOAP_typedef['docListReturnArray'] = array(
    'status'=>'string',
    'value'=>'{urn:MySoapServer}listOfResources',
    'error'=>'string',
);

$SOAP_dispatch_map['Demo_searchResources'] = array(
    'in' => array(
        'searchParams' => '{urn:MySoapServer}searchParams',
    ),
    'out' => array(
        'out' => '{urn:MySoapServer}docListReturnArray',
    ),
    'method' => "core#resources::Demo_searchResources",
);
