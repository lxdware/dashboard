<?php
/*
LXDWARE LXD Dashboard - A web-based interface for managing LXD servers
Copyright (C) 2020-2021  LXDWARE.COM

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

//Start session if not already started
if (!isset($_SESSION)) {
  session_start();
}

//Verify that user is logged in
if (isset($_SESSION['username'])) {

  //Declare and instantiate GET variables
  $action = (isset($_GET['action'])) ? filter_var(urldecode($_GET['action']), FILTER_SANITIZE_STRING) : "";
  $description = (isset($_GET['description'])) ? filter_var(urldecode($_GET['description']), FILTER_SANITIZE_STRING) : "";
  $name = (isset($_GET['name'])) ? filter_var(urldecode($_GET['name']), FILTER_SANITIZE_STRING) : "";
  $network = (isset($_GET['network'])) ? filter_var(urldecode($_GET['network']), FILTER_SANITIZE_STRING) : "";
  $project = (isset($_GET['project'])) ? filter_var(urldecode($_GET['project']), FILTER_SANITIZE_STRING) : "";
  $remote = (isset($_GET['remote'])) ? filter_var(urldecode($_GET['remote']), FILTER_SANITIZE_NUMBER_INT) : "";
  $repo = (isset($_GET['repo'])) ? filter_var(urldecode($_GET['repo']), FILTER_SANITIZE_STRING) : "";

  //Declare and instantiate POST variables
  $json = (isset($_POST['json'])) ? $_POST['json'] : "";

  //Require code from lxd-dashboard/php/config/curl.php
  require_once('../config/curl.php');

  //Require code from lxd-dashboard/php/config/db.php
  require_once('../config/db.php');

  //Require code from lxd-dashboard/php/aaa/accounting.php
  require_once('../aaa/accounting.php');

  //Query database for remote host record
  $base_url = retrieveHostURL($remote);

  //Run the matching action
  switch ($action) {

    case "createNetworkUsingForm":
      $url = $base_url . "/1.0/networks?project=" . $project;
      $data = '{"description": "'.$description.'", "name": "'.$name.'"}';
      $results = sendCurlRequest($action, "POST", $url, $data);
      echo $results;

      //Send event to accounting
      $event = json_decode($results, true);
      $object = $name;
      if ($event['error_code'] == 0){
        logEvent($action, $remote, $project, $object, $event['status_code'], $event['status']);
      }
      else {
        logEvent($action, $remote, $project, $object, $event['error_code'], $event['error']);
      }
      break;

    case "createNetworkUsingJSON":
      $url = $base_url . "/1.0/networks?project=" . $project;
      $data = $json;
      $results = sendCurlRequest($action, "POST", $url, $data);
      echo $results;

      //Send event to accounting
      $event = json_decode($results, true);
      $object = json_decode($data, true)['name'];
      if ($event['error_code'] == 0){
        logEvent($action, $remote, $project, $object, $event['status_code'], $event['status']);
      }
      else {
        logEvent($action, $remote, $project, $object, $event['error_code'], $event['error']);
      }
      break;

    case "deleteNetwork":
      $url = $base_url . "/1.0/networks/" . $network . "?project=" . $project;
      $results = sendCurlRequest($action, "DELETE", $url);
      echo $results;

      //Send event to accounting
      $event = json_decode($results, true);
      $object = $network;
      if ($event['error_code'] == 0){
        logEvent($action, $remote, $project, $object, $event['status_code'], $event['status']);
      }
      else {
        logEvent($action, $remote, $project, $object, $event['error_code'], $event['error']);
      }
      break;

    case "listNetworks":
      if (validateAuthorization($action)) {
        $url = $base_url . "/1.0/networks?recursion=1&project=" . $project;
        $results = sendCurlRequest($action, "GET", $url);
        $results = json_decode($results, true);
        $networks = (isset($results['metadata'])) ? $results['metadata'] : [];

        $i = 0;
        echo '{ "data": [';

        foreach ($networks as $network){
          
          if ($network['name'] == "")
            continue;

          $network_data_managed = ($network['managed']) ? "true" : "false";

          //This array key is not availabe on unmanaged network devices
          if (isset($network['config']['ipv4.address']))
            $ipv4 = $network['config']['ipv4.address'];
          else
            $ipv4 = "";

          //This array key is not available on unmanaged network devices
          if (isset($network['config']['ipv6.address']))
            $ipv6 = $network['config']['ipv6.address'];
          else
            $ipv6 = "";

          if ($i > 0){
            echo ",";
          }
          $i++;

          echo "[ ";

          if ($network['managed'] == "true"){
            echo '"' . "<i class='fas fa-network-wired fa-lg' style='color:#4e73df'></i>" . '",';
            echo '"' . htmlentities($network['name']) . '",';
          }
          else {
            echo '"' . "<i class='fas fa-network-wired fa-lg' style='color:#ddd'></i>" . '",';
            echo '"' . htmlentities($network['name']) . '",';
          }

          echo '"' . htmlentities($network['description']) . '",';
          echo '"' . htmlentities($ipv4) . '",';
          echo '"' . htmlentities($ipv6) . '",';
          echo '"' . htmlentities($network['type']) . '",';
          echo '"' . htmlentities($network_data_managed) . '",';

          echo '"';
          if ($network['managed'] == "true"){
            echo "<a href='#' onclick=loadNetworkJson('".$network['name']."')><i class='fas fa-edit fa-lg' style='color:#ddd' title='Edit' aria-hidden='true'></i></a>";
            echo " &nbsp ";
            echo "<a href='#' onclick=loadRenameNetwork('".$network['name']."')><i class='fas fa-tag fa-lg' style='color:#ddd' title='Rename' aria-hidden='true'></i></a>";
            echo " &nbsp ";
            echo "<a href='#' onclick=deleteNetwork('".$network['name']."')><i class='fas fa-trash-alt fa-lg' style='color:#ddd' title='Delete' aria-hidden='true'></i></a>";
          }
          echo '"';

          echo " ]";

        }

        echo " ]}";
      }
      else {
        echo '{ "data": [] }';
      }      
      break;

    case "loadNetwork":
      $url = $base_url . "/1.0/networks/" . $network . "?project=" . $project;
      $results = sendCurlRequest($action, "GET", $url);
      echo $results;
      break;

    case "renameNetwork":
      $url = $base_url . "/1.0/networks/" . $network . "?project=" . $project;
      $data = '{"name": "' . $name . '"}';
      $results = sendCurlRequest($action, "POST", $url, $data);
      echo $results;

      //Send event to accounting
      $event = json_decode($results, true);
      $object = $network . " - " . $name;
      if ($event['error_code'] == 0){
        logEvent($action, $remote, $project, $object, $event['status_code'], $event['status']);
      }
      else {
        logEvent($action, $remote, $project, $object, $event['error_code'], $event['error']);
      }
      break;

    case "updateNetwork":
      $url = $base_url . "/1.0/networks/" . $network . "?project=" . $project;
      $data = $json;
      $results = sendCurlRequest($action, "PUT", $url, $data);
      echo $results;

      //Send event to accounting
      $event = json_decode($results, true);
      $object = json_decode($data, true)['name'];
      if ($event['error_code'] == 0){
        logEvent($action, $remote, $project, $object, $event['status_code'], $event['status']);
      }
      else {
        logEvent($action, $remote, $project, $object, $event['error_code'], $event['error']);
      }
      break;

    default:
      $results = '{"status": "Bad Request", "status_code": 400, "metadata": {"err": "Unable to execute action on remote host", "status_code": "400"}}';
      echo $results;

  }

}
else {
  echo '{"error": "not authenticated", "error_code": "401", "metadata": {"err": "not authenticated", "status_code": "401"}}';
}
 
?>