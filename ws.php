<?php
if(!$conf=apc_fetch('ws_config')) {
  include '/local/Web/ws.conf';
  apc_store('ws_config',$conf);
}
$raw = filter_input(INPUT_GET, 'q', FILTER_UNSAFE_RAW);
$q = urlencode($raw);
$r = isset($_REQUEST['results']) ? (int)$_REQUEST['results'] : 10;
$s = isset($_REQUEST['start']) ? (int)$_REQUEST['start'] : 1;
$l = isset($_REQUEST['lang']) ? htmlspecialchars($_REQUEST['lang']) : 'en';
$m = isset($_REQUEST['mirror']) ? htmlspecialchars($_REQUEST['mirror']) : '';
$sites = array( 'all'=>'php.net',
                'local'=>'www.php.net',
                'manual'=>'www.php.net',
                'news'=>'news.php.net',
                'bugs'=>'bugs.php.net',
                'pear'=>'pear.php.net',
                'pecl'=>'pecl.php.net',
                'talks'=>'talks.php.net',
              );

if(isset($sites[$_REQUEST['profile']])) {
  $scope = $_REQUEST['profile'];
} else { 
  $scope = 'all';
}

$request =  "{$conf['svc']}$q?appid={$conf['appid']}&start=$s&count=$r&sites={$sites[$scope]}&lang=$l&format=json";
$data = @file_get_contents($request);
list($version,$status_code,$msg) = explode(' ',$http_response_header[0], 3);
if($status_code==200) echo ws_boss_massage($data);
else echo serialize($http_response_header[0]);

function ws_boss_massage($data) {
    $results = json_decode($data, true);
    $rsp = $results['ysearchresponse'];
    $set = $rsp['resultset_web'];

    $massaged = array(
        'ResultSet' => array(
            'totalResultsAvailable' => $rsp['totalhits'],
            'totalResultsReturned' => $rsp['count'],
            'firstResultPosition' => $rsp['start'],
            'Result' => array(),
        ),
    );

    foreach ($set as $result) {
        $massaged['ResultSet']['Result'][] = array(
            'Title' => $result['title'],
            'Summary' => $result['abstract'],
            'Url' => $result['url'],
            'ClickUrl' => $result['clickurl'],
            'MimeType' => NULL, // Not returned from BOSS
            'ModificationDate' => strtotime($result['date']),
            'Cache' => NULL, // Not returned from BOSS
        );
    }

    return serialize($massaged);
}

$dbh = new PDO('mysql:host=localhost;dbname=ws', $conf['db_user'], $conf['db_pw'], array(PDO::ATTR_PERSISTENT => true,
                                                                                         PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true));
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
  $stmt = $dbh->prepare("INSERT INTO log (query,profile,mirror) VALUES (:query,:profile,:mirror)");
  $stmt->execute(array(':query'=>$raw,':profile'=>$scope,':mirror'=>$m));
} catch (PDOException $e) {
   
}
?>