<?php

$app->bind("/devices", function($params) use ($routes) {
    $token = $this->param("token", false);
    //echo $token;
    if (!$token) {
        return false;
    }


    $tokens = $this->db->getKey("cockpit/settings", "cockpit.api.tokens", []);

    if (!isset($tokens[$token])) {
        $this->response->status = 401;
        return ["error" => "access denied"];
    }

    if(!isset($_REQUEST['device_id'])) {
        $this->response->status = 401;
        return ["error" => "device_id needed"];
    }

    if(!isset($_REQUEST['family'])) {
        return ["error" => "family needed"];
    }
    else {
        if($_REQUEST['family'] != 'ios' && $_REQUEST['family'] != 'android') {
            return ["error" => "family error: ios / android expected"];
        }
    }


    if(isset($_REQUEST['_id']) && !empty($_REQUEST['_id'])) {
        $currentDevice = cockpit('datastore:findOne', 'devices', ['_id' => $_REQUEST['_id']]);
        if($currentDevice) {
            $device = [
                '_id'   =>  $_REQUEST['_id'],
                'device_id' => $_REQUEST['device_id'],
                'type'      => strtolower($_REQUEST['family']),
            ];
        }
        else {
            return ['error' => '_id not found'];
        }
    }
    else {
        $device = [
            'device_id' => $_REQUEST['device_id'],
            'type'      => strtolower($_REQUEST['family']),
        ];
    }

    $entry = cockpit('datastore:save_entry', 'devices', $device);
    if($entry) {
        $currentDevice = cockpit('datastore:findOne', 'devices', ['device_id' => $_REQUEST['device_id']]);
        return $currentDevice;
    }

});

$app->bind("/devices/push", function($params) use ($routes) {

    date_default_timezone_set('Europe/Paris');

    /**
     * Function getNotificationsToSend
     * Busca a la collection Push les notificacions no enviades i amb data d'enviament inferior a la actual
     * @return bool
     */
    $getNotifications =  function () {
        $currentDate    = date('Y-m-d');
        $currentTime    = date('H:i');
        $collection = cockpit('collections:collection', 'Push');

        $notifications = $collection->find(['push_date' => "$currentDate", 'push_time' => ['$lt' => $currentTime] , 'status' => false])->limit(10)->toArray();

        if(!empty($notifications)) {
            return $notifications;
        }
        return false;
    };


    /**
     * @param $notifications
     * @return array
     */
    $andoidPushNotification = function($notifications) {
        define( 'API_ACCESS_KEY', 'YOUR-API-ACCESS-KEY-GOES-HERE' );

        $devices = cockpit('datastore:find', 'devices',  [
                'filter' => ['type' => 'android']
            ]
        );
        if(empty($devices)) {
            return ['msg' => 'no devices found'];
        }

        foreach($devices as $device) {
            $registrationIds[] = $device['device_id'];
        }

        $headers = array
        (
            'Authorization: key=' . API_ACCESS_KEY,
            'Content-Type: application/json'
        );
        $results= array();
        foreach($notifications as $notification) {
            $msg = array
            (
                'message' 	=> $notification['message'],
                //'title'		=> 'This is a title. title',
                //'subtitle'	=> 'This is a subtitle. subtitle',
                //'tickerText'	=> 'Ticker text here...Ticker text here...Ticker text here',
                'vibrate'	=> 1,
                'sound'		=> 1,
                'largeIcon'	=> 'large_icon',
                'smallIcon'	=> 'small_icon'
            );
            $fields = array
            (
                'registration_ids' 	=> $registrationIds,
                'data'			=> $msg
            );
            $ch = curl_init();
            curl_setopt( $ch,CURLOPT_URL, 'https://android.googleapis.com/gcm/send' );
            curl_setopt( $ch,CURLOPT_POST, true );
            curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
            curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $fields ) );
            $result = curl_exec($ch );
            curl_close( $ch );
            $results[] = $result;
        }
        return $results;
    };

    /**
     * Envia notificacions push a iOS
     * @param $notifications
     * @return array
     */
    $iOsPushNotification = function($notifications) {

        $devices = cockpit('datastore:find', 'devices',  [
                'filter' => ['type' => 'ios']
            ]
        );
        if(empty($devices)) {
            return ['msg' => 'no devices found'];
        }


        $passphrase = 'elalgodonnoenganya';
        $message = 'hello world';
        $production = true;

        $protocol = 'ssl';

        if ($production) {
            $gateway = $protocol. '://'. 'gateway.push.apple.com:2195';
        } else {
            $gateway = $protocol. '://'.'gateway.sandbox.push.apple.com:2195';
        }

        $ctx = stream_context_create();
        stream_context_set_option($ctx, $protocol, 'local_cert', 'apns-prod-cotton.pem');
        stream_context_set_option($ctx, $protocol, 'passphrase', $passphrase);
        stream_context_set_option($ctx, $protocol, 'cafile', 'entrust_2048_ca.cer');


        $fp = stream_socket_client(
            $gateway, $err,
            $errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);

        if (!$fp) {
            return ['error' => "$err $errstr"];
        }


        $report = array('errors' => 0, 'success' => 0, 'devices' => array());
        foreach($notifications as $notification) {
            foreach($devices as $device) {
                $deviceToken = $device['device_id'];
                $deviceToken = ltrim(rtrim($deviceToken, '>'), '<');
                $deviceToken = str_replace(' ', '', $deviceToken);

                $body['aps'] = array(
                    'badge' => +1,
                    'alert' => $notification['message'],
                    'sound' => 'default'
                );

                $payload = json_encode($body);

                $msg = chr(0) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', strlen($payload)) . $payload;

                $result = fwrite($fp, $msg, strlen($msg));
                fflush($fp);
                if(!$result) {
                    $report['errors']++;
                }
                else {
                    $report['devices'][] = array('token' => $deviceToken, 'msg' => $notification['message']);
                    $report['success']++;
                }
            }
        }
        fclose($fp);
        return $report;
    };




    $token = $this->param("token", false);
    //echo $token;
    if (!$token) {
        return false;
    }
    $tokens = $this->db->getKey("cockpit/settings", "cockpit.api.tokens", []);

    if (!isset($tokens[$token])) {
        $this->response->status = 401;
        return ["error" => "access denied"];
    }


    $notifications = $getNotifications();
    //return ['notu' => $notifications];

    if(empty($notifications)) {
        return ['status' => -1, 'msg' => 'no push messages to send'];
    }
    else {
        $resultiOs      = $iOsPushNotification($notifications);
        $resultAndroid  = $andoidPushNotification($notifications);
        return ['iOS' => $resultiOs, 'android' => $resultAndroid];
    }

});

if (COCKPIT_ADMIN && !COCKPIT_REST) include_once(__DIR__.'/admin.php');
