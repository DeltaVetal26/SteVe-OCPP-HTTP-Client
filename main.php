<?php
  // Steve OCPP HTTP client emulator (v1.5)
  // NOTE: Do not use the & symbol in login, steve password, and AuthKey. It won't work.

  // Configuration
  $steveServerAddres = 'http://00.00.000.00:8080';
  $steveLogin = '';
  $stevePass = '';
  $authKey = '';
  $ocppProtocol = 'JSON'; // or JSON
  $ocppVersion = 'v1.6';  // or 1.6
  $supervision = 'steve';
  // Only for SOAP use - charge point endpoint url
  // Write here your charge point endpoint url
  $endpointURL = 'http://localhost:9090/ocpp'; // (ex: http://localhost:9090/ocpp)

  // Steve commands path array
  $stevePathArray = array(
    // Local cmd (not use)
    'signin' => '/' . $supervision . '/manager/signin',
    'getTransaction' => '/' . $supervision . '/manager/transactions',
    'getConnectorState' => '/' . $supervision . '/manager/home/connectorStatus',
    // OCPP cmd
    'ReserveNow' => '/' . $supervision . '/manager/operations/' . $ocppVersion . '/ReserveNow',
    'RemoteStartTransaction' => '/' . $supervision . '/manager/operations/' . $ocppVersion . '/RemoteStartTransaction',
    'RemoteStopTransaction' => '/' . $supervision . '/manager/operations/' . $ocppVersion . '/RemoteStopTransaction',
    'UnlockConnector' => '/' . $supervision . '/manager/operations/' . $ocppVersion . '/UnlockConnector',
    'DataTransfer' => '/' . $supervision . '/manager/operations/' . $ocppVersion . '/DataTransfer',
    'Reset' => '/' . $supervision . '/manager/operations/' . $ocppVersion . '/Reset',
    'SetChargingProfile' => '/' . $supervision . '/manager/operations/' . $ocppVersion . '/SetChargingProfile'
  );

  // Variables
  $getData = $_GET;
  $curl = "";

  // Using endpoint? (for SOAP only)
  if($ocppProtocol == 'JSON') { $endpointURL = '-'; }

  // Functions
  // # cURL init
  function curlConnectionInit($curl) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_COOKIESESSION, true);
    curl_setopt($curl, CURLOPT_COOKIEFILE, "cookiefile");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.0; ru; rv:1.9.1.3) Gecko/20090824 Firefox/3.5.3');
    return $curl;
  }

  // # Connect to URL
  function curlConnectTo($steveServerAddres, $stevePathArray) {
    global $curl;
    $steveServerURL = $steveServerAddres . $stevePathArray;
    curl_setopt($curl, CURLOPT_URL, $steveServerURL);
    $content = curl_exec($curl);
    //echo $content;
    return $content;
  }

  // # Get CSRF token
  function getCSRFToken($content) {
    preg_match('/<input type="hidden" name="_csrf" value="(.*)"/Uis', $content, $csrf);
    $csrf = $csrf[1];
    if($csrf != NULL) {
      return $csrf;
    } else {
      return NULL;
    }
  }

  // # SignIn to Steve panel
  function steveSignIn($login, $pass) {
    global $curl, $steveServerAddres, $stevePathArray;
    $content = curlConnectTo($steveServerAddres, $stevePathArray['signin']);
    $token = getCSRFToken($content);
    $form = "username=".$login."&password=".$pass."&_csrf=".$token."";
    curl_setopt($curl, CURLOPT_POSTFIELDS, $form);
    curl_exec($curl);
  }

  // # Get element by class (for HTML DOM parse)
  function getElementsByClass(&$parentNode, $tagName, $className) {
    $nodes = array();
    $childNodeList = $parentNode->getElementsByTagName($tagName);
    for($i = 0; $i < $childNodeList->length; $i++) {
      $temp = $childNodeList->item($i);
      if(stripos($temp->getAttribute('class'), $className) !== false) {
        return $temp;
      }
    }
  }

  // # Parse html (Get data)
  function htmlParser($getData, $mode) {
    global $curl, $content, $steveServerAddres, $stevePathArray;

    // NULL ChargeBoxID protection
    if($getData['ChargeBoxID'] == NULL) {
      return 'NullBoxID';
    }

    // Null mode protectio
    if($mode == NULL) {
      return 'NullMode';
    }

    // Get content
    // Not used for DataTransfer (DataTransfer connected before calling this function)
    if($mode != 'getDataTransferResponse') {
      $content = curlConnectTo($steveServerAddres, $stevePathArray[$mode]);
    }

    // Create DOM element
    $domdoc = new DOMDocument();
    libxml_use_internal_errors(true);
    $domdoc->loadHTML($content);
    $domdoc->saveHTML();

    // Get table (for DataTransfer respond)
    if($mode == 'getDataTransferResponse') {
      $domdoc = getElementsByClass($domdoc, 'table', 'res');
    }

    // Get 'th' array
    $thArray = $tdArray = $array = array();
    $th = $domdoc->getElementsByTagName('th');
    foreach ($th as $th) {
      $thArray[] = $th->nodeValue;
    }
    $count = count($thArray);

    // Get 'td' and create result array
    $dataArray = [];
    foreach($domdoc->getElementsByTagName('tr') as $tr) {
      $data = $tr->getElementsByTagName('td');
      if(count($data) > 0) {
          $tdArray = [];
          foreach($data as $td) {
            $tdArray[] = $td->textContent;
          }
        $dataArray[] = @array_combine($thArray, $tdArray);
      }
    }

    // Select mode and get values
    // GetTransaction
    if($mode == 'getTransaction') {
      // Search transaction ID
      foreach ($dataArray as $key) {
        if(($key['ChargeBox ID'] == $getData['ChargeBoxID']) && ($key['Connector ID'] == $getData['ConnectorID'])) {
          // Return transaction ID
          return $key['Transaction ID'];
        }
      }
      return 'TransactionNotExist';
    }
    // Get connector state
    else if($mode == 'getConnectorState') {
      // Search last connector state
      foreach ($dataArray as $key) {
        if(($key['ChargeBox ID'] == $getData['ChargeBoxID']) && ($key['Connector ID'] == $getData['ConnectorID'])) {
          // Return transaction ID
          return $key['Status'];
        }
      }
      return 'StateNotExist';
    }
    // Get DataTransfer respose
    else if($mode == 'getDataTransferResponse') {
      $response = $dataArray[1]['Response'];
      if($response == NULL) {
        return 'ResponseNotFound';
      }
      return $response;
    }
  }

  // # Command selector
  function cmdInputSelector($getData) {
    global $curl, $content, $steveServerAddres, $stevePathArray, $ocppProtocol, $endpointURL;

    // Set path
    $stevePath = $stevePathArray[$getData['cmd']];

    // Select command
    switch ($getData['cmd']) {
      case 'getConnectorState':
        $allow = true; // Allow command?
        if($allow) {
          // Get connector state
          $connectorState = htmlParser($getData, 'getConnectorState');
          return $connectorState;
        }
        break;
      case 'ReserveNow':
        $allow = true; // Allow command?
        if($allow) {
          // Redirect to ReserveNow page
          $content = curlConnectTo($steveServerAddres, $stevePath);
          // Get token
          $token = getCSRFToken($content);
          // Prepare form
          $form = "chargePointSelectList=".$ocppProtocol.";".$getData['ChargeBoxID'].";".$endpointURL."&connectorId=".$getData['ConnectorID']."&expiry=".$getData['Expiry']."&idTag=".$getData['idTag']."&_csrf=".$token."";
          // Send form
          curl_setopt($curl, CURLOPT_POSTFIELDS, $form);
          curl_exec($curl);
          return 'Ok';
        }
        break;
      case 'RemoteStartTransaction':
        $allow = true; // Allow command?
        if($allow) {
          // Redirect to RemoteStartTransaction page
          $content = curlConnectTo($steveServerAddres, $stevePath);
          // Get token
          $token = getCSRFToken($content);
          // Prepare form
          $form = "chargePointSelectList=".$ocppProtocol.";".$getData['ChargeBoxID'].";".$endpointURL."&connectorId=".$getData['ConnectorID']."&idTag=".$getData['idTag']."&_csrf=".$token."";
          // Send form
          curl_setopt($curl, CURLOPT_POSTFIELDS, $form);
          curl_exec($curl);
          return 'Ok';
        }
        break;
      case 'RemoteStopTransaction':
        $allow = true; // Allow command?
        if($allow) {
          // Get transaction ID
          $steveTransactionID = htmlParser($getData, 'getTransaction');
          // Redirect to RemoteStopTransaction page
          $content = curlConnectTo($steveServerAddres, $stevePath);
          // Get token
          $token = getCSRFToken($content);
          // Prepare form
          $form = "chargePointSelectList=".$ocppProtocol.";".$getData['ChargeBoxID'].";".$endpointURL."&transactionId=".$steveTransactionID."&_csrf=".$token."";
          // Send form
          curl_setopt($curl, CURLOPT_POSTFIELDS, $form);
          curl_exec($curl);
          return 'Ok';
        }
        break;
      case 'UnlockConnector':
        $allow = true; // Allow command?
        if($allow) {
          // Redirect to RemoteStartTransaction page
          $content = curlConnectTo($steveServerAddres, $stevePath);
          // Get token
          $token = getCSRFToken($content);
          // Prepare form
          $form = "chargePointSelectList=".$ocppProtocol.";".$getData['ChargeBoxID'].";".$endpointURL."&connectorId=".$getData['ConnectorID']."&_csrf=".$token."";
          // Send form
          curl_setopt($curl, CURLOPT_POSTFIELDS, $form);
          curl_exec($curl);
          return 'Ok';
        }
        break;
      case 'DataTransfer':
        $allow = true; // Allow command?
        if($allow) {
          // Redirect to RemoteStartTransaction page
          $content = curlConnectTo($steveServerAddres, $stevePath);
          // Get token
          $token = getCSRFToken($content);
          // Prepare form
          $form = "chargePointSelectList=".$ocppProtocol.";".$getData['ChargeBoxID'].";".$endpointURL."&vendorId=".$getData['VendorID']."&messageId=".$getData['MessageID']."&data=".$getData['Data']."&_csrf=".$token."";
          // Send form
          curl_setopt($curl, CURLOPT_POSTFIELDS, $form);
          curl_exec($curl);
          // Sleep.. (Wait response)
          sleep(5);
          // Get response
          $content = curl_exec($curl);
          // Parse response
          $response = htmlParser($getData, 'getDataTransferResponse');
          // Return response
          return $response;
        }
        break;
      case 'Reset':
        $allow = true; // Allow command?
        if($allow) {
          // Redirect to Reset page
          $content = curlConnectTo($steveServerAddres, $stevePath);
          // Get token
          $token = getCSRFToken($content);
          // Prepare form
          $cbid = explode(";",$getData['ChargeBoxID']);
		      $toReset = "";
		      for($i = 0; $i < count($cbid); $i++) {
			      $toReset = $toReset . "chargePointSelectList=".$ocppProtocol.";" .$cbid[$i] . ";".$endpointURL."&";
		      }
          $form = $toReset . "_chargePointSelectList=1&resetType=HARD&_csrf=".$token."";
          // Send form
          curl_setopt($curl, CURLOPT_POSTFIELDS, $form);
          curl_exec($curl);
          return 'Ok';
        }
        break;
      case 'SetChargingProfile':
         $allow = true; // Allow command?
         if($allow) {
           // Redirect to Reset page
           $content = curlConnectTo($steveServerAddres, $stevePath);
           // Get token
           $token = getCSRFToken($content);
           // Prepare form
           $form = "chargePointSelectList=".$ocppProtocol.";".$getData['ChargeBoxID'].";".$endpointURL."&connectorId=".$getData['ConnectorID']."&chargingProfilePk=".$getData['ChargingProfileID']."&_csrf=".$token."";
           // Send form
           curl_setopt($curl, CURLOPT_POSTFIELDS, $form);
           curl_exec($curl);
           return 'Ok';
         }
        break;
      default:
        // Unknown command
        return 'Unknown command';
        break;
    }

    // Command not allow?
    if(!$allow) {
      return 'Command NotAllow';
    }

  }

  /*** Сode execution ***/
  // Auth
  if($getData['key'] != $authKey) {
    return;
  }

  // Init connection
  $curl = curlConnectionInit($curl);

  // SignIn Steve panel
  steveSignIn($steveLogin, $stevePass);

  // Select page and send cmd
  $result = cmdInputSelector($getData);

  echo $result;

 ?>
