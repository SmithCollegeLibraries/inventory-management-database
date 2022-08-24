<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\httpclient\XmlParser;
use yii\httpclient\Client;
use app\models\BarcodeTray;
use app\models\TrayShelf;
use app\models\Dewey;


class FOLIO extends Model
{
	public $barcode;

	//NEW FOLIO FUNCTIONS START HERE
	public function processTray($tray)
	{
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		$items = $this->getTrayByID($tray);
		$set = array();
		foreach($items as $item){
			$set[] = $this->processBarcode($item['barcode']);
		}
		return $set;
	}

	public function processPagingSlips()
	{
		$date = date("Ymd");
		$paging = $this->searchPagingSlips("http://libtools2.smith.edu/folio/web/search/search-circulation?id=9e4b06c8-0cb0-4011-ab1d-a23af57d190c");
		$set = $paging["data"];

		foreach($set as $list){
			$data[] = $this->processBarcode($list);
		}
		return($data);
	}

	public function processShelfTray($tray)
	{
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		$items = $this->getTrayByBarcode($tray);
		$set = array();
		foreach($items as $item){
			$set[] = $this->processBarcode($item['barcode']);
		}
		return $set;
	}

	public function processTitleSearch($request)
	{
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		$search = $this->search("((title all $request) and items.effectiveLocationId==5eb79fcc-af08-4ae1-9ab3-dde11e330a01)");
		if(isset($search["data"]["totalRecords"]) && $search["data"]["totalRecords"] > 0){
			$results = array();
			array_push($results, $this->getInfo($search["data"]["instances"][0]));
			return $results;

		} else {
			return false;
		}

	}

	public function processCallNumber($request)
	{
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		$search = $this->search("(items.fullCallNumber=$request)");
		if(isset($search["data"]["totalRecords"]) && $search["data"]["totalRecords"] > 0){
			return $this->getInfo($search["data"]["instances"][0]);
		} else {
			return false;
		}
	}

	public function processMultiCallNumber($request)
	{
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		$results = array();
		foreach($request as $items){
			$search = $this->search("(items.fullCallNumber=$request)");
			if(isset($search["data"]["totalRecords"]) && $search["data"]["totalRecords"] > 0){
				array_push($results, $this->getInfo($search["data"]["instances"][0]));
			} else {
				array_push($results, $this->getInfoWithoutAleph($barcode));
			}
		}
		return $results;
	}

	public function processByOCLC($request)
	{
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		$search = $this->search("(identifiers.value=*$request)");
		if(isset($search["data"]["totalRecords"]) && $search["data"]["totalRecords"] > 0){
			return $this->getInfo($search["data"]["instances"][0]);
		} else {
			return false;
		}
	}


	public function processBarcode($barcode, $type="paging")
	{
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		$this->barcode = $barcode;
		$search = $this->search("(items.barcode==$barcode)");
		if(isset($search["data"]["totalRecords"]) && $search["data"]["totalRecords"] > 0){
			return $this->getInfo($search["data"]["instances"][0]);
		} else {
			return $this->getInfoWithoutAleph($barcode);
		}
	}



	private function getInfo($set, $barcodeItem='')
		{
		$results = array(
			   'title' => '',
			   'call_number' => '',
			   'call_number_normalized' => '',
			   'issn' => '',
			   'isbn' => '',
			   'description' => '',
			   'barcode' => '',
			   'tray_barcode' => '',
			   'stream' => '',
			   "shelf_barcode" => '',
			   'shelf' => '',
			   'record_barcode' => '',
			   'new_call' => ''
		);

			$id = $set["id"];

			// $rtac = $this->RTACSearch("id=$id");

			$callNumber = '';
			$issn = '';
			$isbn = '';
			$barcode = $this->barcode;
			$description = array();

			if(!empty($set["items"])){
				foreach($set["items"] as $item){
					// The results may include more than one item, because
					// an instance that matches a particular barcode may
					// contain other items as well.  So we need to check
					// that this item is the one we searched for, not another
					// item belonging to the found instance.
					if($item["barcode"] != $barcodeItem){
						continue;
					}
					if($item["effectiveLocationId"] == "5eb79fcc-af08-4ae1-9ab3-dde11e330a01"){
						$callNumber = $item["effectiveCallNumberComponents"]["callNumber"];
						$barcode = $item["barcode"];
					}
				}
			}

			// if(!empty($rtac["data"])){
			// 	if(isset($rtac["data"]["holding"][1])){
			// 		foreach($rtac["data"]["holding"] as $items){
			// 			if(isset($items["location"]) &&  $items["location"] == 'Smith College Annex Stacks'){
			// 				$callNumber = $items["callNumber"];
			// 			}
			// 		}
			// 	} else {
			// 		if($rtac["data"]["holding"]["location"] == 'Smith College Annex Stacks'){
			// 			$callNumber = $rtac["data"]["holding"]["callNumber"];
			// 		}
			// 	}
			// }

			if(!empty($set["identifiers"])){
				foreach($set["identifiers"] as $items){
					switch($items["identifierTypeId"]){
						case '913300b2-03ed-469a-8179-c1092c991227':
							$issn = $items["value"];
						break;
						case '8261054f-be78-422d-bd51-4ed9f33c3422':
							$isbn = $items["value"];
						break;
					}
				}
			}

			if(!empty($set["notes"])){
				foreach($set["notes"] as $items){
					array_push($description, $items["note"]);
				}
			}



			$results["title"] = $set["title"];
			$results["call_number"] = $callNumber;
			$results["issn"] = $issn;
			$results["isbn"] = $isbn;
			$results["description"] = implode("-------", $description);
			$results["barcode"] = $barcode;


			if(isset($this->barcode)){
				$barcode = $this->barcode;
			} else {
				$barcode = $results['barcode'];
			}

			$tray = $this->getTray($barcode);
			$shelf = $this->getShelf($tray["boxbarcode"], $callNumber, isset($results["old_location"]) ? $results["old_location"] : '');
			$results["tray_id"] = isset($tray["id"]) ? $tray["id"] : '';
			$results["tray_barcode"] = isset($tray["boxbarcode"]) ? $tray["boxbarcode"] : '';
			$results["stream"] = isset($tray["stream"]) ? $tray["stream"] : '';
			$results["status"] = isset($tray["status"]) ? $tray["status"] : '';
			$results["timestamp"] = isset($tray["timestamp"]) ? $tray["timestamp"] : '';
			$results["shelf_barcode"] = isset($shelf["shelf"]) ? $shelf["shelf"] : '' ;
			if(isset($shelf["id"])){
				$results["shelf"] = $shelf;
			} else {
				$results["shelf"] = array(
					   'id' => '',
					   'boxbarcode' => '',
					   'shelf' => '',
					   'row' => '',
					   'side' => '',
					   'ladder' => '',
					   'shelf_number' => 0,
					   'shelf_depth' => '',
					   'shelf_position' => '',
					   'initials' => '',
					   'added' => '',
					   'timestamp' => ''
				   );
			}
			return $results;
		}

	private function RTACSearch($url)
	{
		  $client = new Client();
		  $location = 'http://libtools2.smith.edu/folio/web/search/search-rtac?';
		  $response = $client->createRequest()
			->setMethod('get')
			->setFormat(Client::FORMAT_JSON)
			->setUrl($location . $url)
			->send();
		if ($response->isOk) {
			return $response->data;
		}
	}


	private function search($url)
	{
		$client = new Client(['baseUrl' => "http://libtools2.smith.edu/folio/web/search/search-inventory"]);
		$response = $client->createRequest()
			->setMethod('get')
			->setFormat(Client::FORMAT_JSON)
			->setUrl([
				'query' => $url
			])
			->send();
		if ($response->isOk) {
			return $response->data;
		} else {

		}
	}

	private function searchPagingSlips($url)
	{
		$client = new Client(['baseUrl' => $url]);
		  $response = $client->createRequest()
			->setMethod('get')
			->setFormat(Client::FORMAT_JSON)
			->send();
		if ($response->isOk) {
			return $response->data;
		}
	}


	//FOLIO FUNCTIONS END HERE

	//ALEPH FUNCTIONS START HERE. COMMENT OUT AFTER CONVERSION TO FOLIO
	// public function processTray($tray)
	// {
	// 	\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
	// 	$items = $this->getTrayByID($tray);
	// 	$set = array();
	// 	foreach($items as $item){
	// 		$set[] = $this->processBarcode($item['barcode'], $this->ADMCheck($item['barcode']));
	// 	}
	// 	return $set;
	// }

	// public function processPagingSlips($day)
	// {
	// 	$date = date("Ymd");
	// 	if($day == "morning"){
	// 		$paging = file("http://fcaw.library.umass.edu/pickup/anx.cir.12.paging". $date ."_am_barcode");
	// 	} else {
	// 		$paging = file("http://fcaw.library.umass.edu/pickup/anx.cir.12.paging". $date ."_pm_barcode");
	// 	}
	// 	$set = array();
	// 	foreach($paging as $items){
	// 		$items = str_replace('<z30-barcode>', '', $items);
	// 		$items = str_replace('</z30-barcode>', '', $items);
	// 		$set[] = $items;
	// 	}
	// 	$data = array();
	// 	foreach($set as $list){
	// 		$data[] = $this->processBarcode($list);
	// 	}
	// 	return($data);
	// }
	//
	// public function processShelfTray($tray)
	// {
	// 	\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
	// 	$items = $this->getTrayByBarcode($tray);
	// 	$set = array();
	// 	foreach($items as $item){
	// 		$set[] = $this->processBarcode($item['barcode']);
	// 	}
	// 	return $set;
	// }
	//

	public function processShelf($shelf)
	{
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		$items = $this->getShelfByID($shelf);
		$set = array();
		foreach($items as $item){
			$set[] = $item['boxbarcode'];
		}
		$list = $this->getTrayByList($set);
		$response = array();
		foreach($list as $data){
			$response[] = $this->processShelfTray($data["barcode"]);
		}
		return $response;
/*
		foreach($set as $list){
			$this->format($list);
		}
*/
// 		return $set;
	}

	// public function processBarcode($barcode, $type="paging")
	// {
	//     $library = $this->ADMCheck($barcode);
	//     $this->barcode = $barcode;
	//     \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
	//     $this->barcode = $barcode;
	//     $options = array (
	// 	    'op' => 'read-item',
	// 	    'library' => $library,
	// 	    'item_barcode' => trim($barcode)
	//     );
	//     $url = http_build_query($options, '', '&');
	//     $search = $this->search($url);
	//     if(isset($search["z30"]['z30-doc-number'])) {
	//     	return $this->processRequest($search["z30"]['z30-doc-number'], $library, $barcode, $type);
	//     } else {
	// 	    return $this->getInfoWithoutAleph($barcode);
	//     }
	// }

	private function processRequest($request, $library, $barcode='', $type)
	{
		   $options = array (
			'op' => 'find_doc',
			'base' => $library,
			'doc-number' => $request
		);
		$url = http_build_query($options, '' , '&');
		$search = $this->search($url);
		$term = '';
		$json = json_encode($search);
		$json = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $json);
		$array = json_decode($json, true);
		foreach ($array["record"]["metadata"]["oai_marc"]["varfield"] as $field)
		{
			switch ($field["@attributes"]['id'])
			{
				case 'LKR' :
				$search_results['doc'] = "";
				foreach ($field["subfield"] as $subfield)
				{
					$term .= $subfield;
				}
				break;
			}
		}
		return $this->getDoc(str_replace('ADMFCL01', '', $term), $barcode, $type);
	}

	// public function processByOCLC($request)
	// {
	// 	\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
	// 	   $options = array (
	// 		'op' => 'find',
	// 		'base' => 'FCL01SMT',
	// 		'request' => "OCL=$request"
	// 	);
	// 	$url = http_build_query($options, '' , '&');
	// 	$search = $this->search($url);
	// 	if(isset($search['set_number'])){
	// 	$options = array (
	// 		'op' => 'present',
	// 		'set_no' => $search['set_number'],
	// 		'set_entry' => '1-60'
	// 	);
	// 	$url = http_build_query($options, '' , '&');
	// 	$data = $this->search($url);
	// 	$content = $data["record"];
	// 	if(isset($content["doc_number"])) {
	// 		$results = $this->getDoc($content["doc_number"]);
	// 		return $results;
	// 	} else {
	// 	$results = array(
	// 		   'title' => 'ALEPH OCLC Search Failed : OCLC Number used ' . $request,
	// 		   'call_number' => '',
	// 		   'call_number_normalized' => '',
	// 		   'issn' => '',
	// 		   'isbn' => '',
	// 		   'description' => '',
	// 		   'barcode' => '',
	// 		   'tray_barcode' => '',
	// 		   'stream' => '',
	// 		   "shelf_barcode" => '',
	// 		   'shelf' => array(
	// 			   'id' => '',
	// 			   'boxbarcode' => '',
	// 			   'shelf' => '',
	// 			   'row' => '',
	// 			   'side' => '',
	// 			   'ladder' => '',
	// 			   'shelf_number' => '',
	// 			   'shelf_depth' => '',
	// 			   'shelf_position' => '',
	// 			   'initials' => '',
	// 			   'added' => '',
	// 			   'timestamp' => ''
	// 		   ),
	// 		   'record_barcode' => '',
	// 		   'new_call' => '',
	// 		   'old_location' => '',
	// 		   'tray_id' => '',
	// 		   'status' => '',
	// 		   'timestamp' => ''
	// 	);
	// 		return $results;
	// 	}
	// 	} else {
	// 		return false;
	// 	}
	// }

	public function basicTemplate()
	{
	   $results = array(
			   'title' => '',
			   'call_number' => '',
			   'call_number_normalized' => '',
			   'issn' => '',
			   'isbn' => '',
			   'description' => '',
			   'barcode' => '',
			   'tray_barcode' => '',
			   'stream' => '',
			   "shelf_barcode" => '',
			   'shelf' => array(
				   'id' => '',
				   'boxbarcode' => '',
				   'shelf' => '',
				   'row' => '',
				   'side' => '',
				   'ladder' => '',
				   'shelf_number' => '',
				   'shelf_depth' => '',
				   'shelf_position' => '',
				   'initials' => '',
				   'added' => '',
				   'timestamp' => ''
			   ),
			   'record_barcode' => '',
			   'new_call' => '',
			   'old_location' => '',
			   'tray_id' => '',
			   'status' => '',
			   'timestamp' => ''
		);
		return $results;
	}

	// public function processCallNumber($request)
	// {
	// 	  \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
	// 	   $options = array (
	// 		'op' => 'find',
	// 		'base' => 'FCL01SMT',
	// 		'request' => "LCI2=$request"
	// 	);
	// 	$url = http_build_query($options, '' , '&');
	// 	$search = $this->search($url);
	// 	$options = array (
	// 		'op' => 'present',
	// 		'set_no' => $search['set_number'],
	// 		'set_entry' => '1-60'
	// 	);
	// 	$url = http_build_query($options, '' , '&');
	// 	$data = $this->search($url);
	// 	$content = $data["record"];
	// 	$results = $this->getDoc($content["doc_number"]);
	// 	return $results;
	// }

	// public function processMultiCallNumber($request)
	// {
	// 			 \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
	// 	   $options = array (
	// 		'op' => 'find',
	// 		'base' => 'FCL01SMT',
	// 		'request' => "LCI2=$request"
	// 	);
	// 	$url = http_build_query($options, '' , '&');
	// 	$search = $this->search($url);
	// 	if(isset($search["set_number"])){
	// 	$options = array (
	// 		'op' => 'present',
	// 		'set_no' => $search['set_number'],
	// 		'set_entry' => '1-60'
	// 	);
	// 	$url = http_build_query($options, '' , '&');
	// 	$data = $this->search($url);
	// 	$content = $data["record"];
	// 	$results = array();
	// 	if(isset($content[0])) {
	// 		 foreach($content as $items){
	// 			$results[] = $this->getDoc($items["doc_number"]);
	// 		}
	// 	} else {
	// 	   $results = $this->getDoc($content["doc_number"]);
	// 	}
	// 	return $results;
	// 	} else {
	// 		return $this->basicTemplate();
	// 	}
	// }



	private function getRecords($set)
	{
		$options = array (
			'op' => 'present',
			'set_no' => $set,
			'set_entry' => '1-60'
		);
		$url = http_build_query($options, '' , '&');
		$data = $this->search($url);
		$content = $data["record"];
		$results = array();
		foreach($content as $items){
			$results[] = $this->getDoc($items["doc_number"]);
		}
		return $results;

	}

	private function getItems($id)
	{
				 $options = array (
			'op' => 'item-data',
			'base' => 'FCL01',
			'doc-number' => $id
		);
		$url = http_build_query($options, '' , '&');
		$search = $this->search($url);
		if(isset($search["item"][0])){
			foreach($search["item"] as $items){
				$this->readItem($items["barcode"]);
			}
		} else {
			$this->readItem($search["item"]["barcode"]);
		}
// 		$this->readItem($search["item"]["barcode"]);
	}

	private function readItem($id)
	{
		 $options = array (
			'op' => 'read-item',
			'library' => 'SMT50',
			'item_barcode' => $id
		);
		$url = http_build_query($options, '' , '&');
		$search = $this->search($url);
		$this->format($search);
		$this->getHoldings($search["z30"]["z30-hol-doc-number"]);
	}


	private function getDoc($id, $barcode='', $type)
	{
		  $options = array (
			'op' => 'find-doc',
			'base' => 'FCL01',
			'doc_num' => $id
		);
		$url = http_build_query($options, '' , '&');
		$search = $this->search($url);
		if(isset($search["record"])){
			if($type == "full"){
				return $this->getFullMarc($search["record"]["metadata"]["oai_marc"], $barcode);
			} else {
				return $this->getInfo($search["record"]["metadata"]["oai_marc"], $barcode);
			}
		} else {
			return '';
		}
	}

	private function getFullMarc($set, $barcodeItem='')
	{
		$results = array();
		foreach($set["varfield"] as $items){
			$results[$items["@attributes"]["id"]] = $this->is($items);
		}
		return $results;
	}

//     private function getInfo($set, $barcodeItem='')
//     {
//
//
// 		$results = array(
// 		   	'title' => '',
// 		   	'call_number' => '',
// 		   	'call_number_normalized' => '',
// 		   	'issn' => '',
// 		   	'isbn' => '',
// 		   	'description' => '',
// 		   	'barcode' => '',
// 		   	'tray_barcode' => '',
// 		   	'stream' => '',
// 		   	"shelf_barcode" => '',
// 		   	'shelf' => '',
// 		   	'record_barcode' => '',
// 		   	'new_call' => '',
// 		   	'height' => 'none',
// 			'material' => ''
// 	    );
//
// 		foreach($set["varfield"] as $items) {
// //			$this->format($items);
// 			switch($items["@attributes"]["id"]){
// 				case '020':
// 					$results['isbn'] = $this->is($items);
// 				break;
// 				case '022':
// 					$results["issn"] = $this->is($items);
// 				break;
// /*
// 				case '050':
// 			   	case '060':
// 			   	case '070':
// 			   	case '090':
// 			   	case '092':
// 			   	case '099':
// 			   		$results["call_number"] = $this->callnumber($items);
// // 			   		$results["call_number_normalized"] = $this->callnumbernormalized($results["call_number"]);
// 			   	break;
// */
// 				case '245':
// 					$results["title"] = $this->title($items);
// 				break;
// 				case '300':
// 					$results["description"] = $this->info($items);
// 				break;
// 				case '852':
// 					$results['barcode'] = isset($this->barcode) ? $this->barcode : $this->record_barcode($items);
// 					$results["call_number"] = $this->callnumbertest($items);
// 					//TODO ADD LOCATION INFORMATION
// // 					$results["call_number_normalized"] = $this->callnumbernormalized($results["call_number"]);
// 				break;
// 				case 'TYP':
// 					$results["material"] = $this->info($items);
// 				case '952':
// 					$results["old_location"] = $this->old_location($items);
// 				break;
// 			}
// 		}
// 		if(isset($this->barcode)){
// 			$barcode = $this->barcode;
// 		} else {
// 			$barcode = $results['barcode'];
// 		}
//
// 		$tray = $this->getTray($barcode);
// 		$shelf = $this->getShelf($tray["boxbarcode"], $results["call_number"], isset($results["old_location"]) ? $results["old_location"] : '');
// 		$results["tray_id"] = isset($tray["id"]) ? $tray["id"] : '';
// 		$results["tray_barcode"] = isset($tray["boxbarcode"]) ? $tray["boxbarcode"] : '';
// 		$results["stream"] = isset($tray["stream"]) ? $tray["stream"] : '';
// 		$results["status"] = isset($tray["status"]) ? $tray["status"] : '';
// 		$results["timestamp"] = isset($tray["timestamp"]) ? $tray["timestamp"] : '';
// 		$results["shelf_barcode"] = isset($shelf["shelf"]) ? $shelf["shelf"] : '' ;
// 		if(isset($shelf)){
// 			foreach($shelf as $key => $shelves){
// 				if($key === "shelf_number") {
// 					if((int)$shelves < 7){
// 						$results['height'] = "floor";
// 					} else if((int)$shelves >= 7) {
// 						$results['height'] = "lift";
// 					} else {
// 						$results['height'] = "none";
// 					}
// 				}
// 				$results[$key] = $shelves;
// 			}
// 		}
// // 		$results["shelf"] = isset($shelf) ? $shelf : '';
// 		return $results;
//     }

	private function getInfoWithoutAleph($barcode)
	{
		$results = array(
			   'title' => '',
			   'call_number' => '',
			   'call_number_normalized' => '',
			   'issn' => '',
			   'isbn' => '',
			   'description' => '',
			   'barcode' => $barcode,
			   'tray_barcode' => '',
			   'stream' => '',
			   "shelf_barcode" => '',
			   'shelf' => '',
			   'record_barcode' => '',
			   'new_call' => '',
			   'height' => 'none'
		);

		$tray = $this->getTray($barcode);
		$shelf = $this->getShelf($tray["boxbarcode"], $results["call_number"], isset($results["old_location"]) ? $results["old_location"] : '');
		$results["tray_id"] = isset($tray["id"]) ? $tray["id"] : '';
		$results["tray_barcode"] = isset($tray["boxbarcode"]) ? $tray["boxbarcode"] : '';
		$results["stream"] = isset($tray["stream"]) ? $tray["stream"] : '';
		$results["status"] = isset($tray["status"]) ? $tray["status"] : '';
		$results["timestamp"] = isset($tray["timestamp"]) ? $tray["timestamp"] : '';
		$results["shelf_barcode"] = isset($shelf["shelf"]) ? $shelf["shelf"] : '' ;
		if(isset($shelf)){
			foreach($shelf as $key => $shelves){
				if($key === "shelf_number") {
					if((int)$shelves < 7){
						$results['height'] = "floor";
					} else if((int)$shelves >= 7) {
						$results['height'] = "lift";
					} else {
						$results['height'] = "none";
					}
				}
				$results[$key] = $shelves;
			}
		}
		// 		$results["shelf"] = isset($shelf) ? $shelf : '';
		return $results;
	}

	private function getTrayByID($tray)
	{
	  return BarcodeTray::find()->where(['like', 'boxbarcode', $tray])->limit(50)->all();
	}

	private function getTrayByBarcode($tray)
	{
	  return BarcodeTray::find()->where(['like', 'barcode', $tray])->limit(50)->all();
	}

	private function getShelfByID($shelf)
	{
	  return TrayShelf::find()->where(['like', 'shelf', $shelf])->limit(50)->all();
	}

	private function getTrayByList($tray)
	{
	   return BarcodeTray::find()->where(['in', 'boxbarcode', $tray])->limit(50)->all();
	}

	private function getTray($barcode)
	{
		return BarcodeTray::find()->where(['like', 'barcode', trim($barcode)])->one();
	}

	private function getShelf($tray, $callnumber = '', $oldlocation = '')
	{
		$shelf = TrayShelf::find()
					->select('id, shelf, row, side, ladder, shelf_number, shelf_depth, shelf_position')
					->where(['boxbarcode' => $tray])
					->asArray()
					->one();
		$dewey_shelf = $this->searchDewey($callnumber, $this->locationMap($oldlocation));
		if($shelf){
			return $shelf;
		} else if($dewey_shelf){
			return $dewey_shelf;
		} else {
			return [];
		}
	}

	private function is($items)
	{
		if(is_array($items["subfield"])){
			foreach($items["subfield"] as $item){
				return (string)$item;
			}
		} else {
			return (string)$items["subfield"];
		}
	}

	private function old_location($items)
	{
		   if(isset($items["subfield"][1])) {
			return (string)$items["subfield"][1];
		} else {
			return '';
		}
	}

	private function record_barcode($items){
// 	    $this->format($items);
		if(isset($items["subfield"][3]) && strpos($items["subfield"][3], '3101') !== false) {
			return (string)$items["subfield"][3];
		} else if(isset($items["subfield"][4]) && strpos($items["subfield"][4], '3101') !== false) {
			return (string)$items["subfield"][4];
		} else if(isset($items["subfield"][5]) && strpos($items["subfield"][5], '3101') !== false){
			return (string)$items["subfield"][5];
		} else {
			return '';
		}
	}

	private function title($items)
	{
	  $results = '';
	  if(is_array($items["subfield"])) {
		  foreach ($items["subfield"] as $key=>$subfield){
			if($results == "") {
					   $results .= (string)$subfield;
				   } else {
					   $results .= " ".(string)$subfield;
				   }
			}
		} else {
			return (string)$items["subfield"];
		}
		return $results	;
	}

	private function callnumber($items)
	{
		$results = '';
		if(is_array($items["subfield"])) {
			foreach($items["subfield"] as $subfield) {
				$results .= (string)$subfield;
			}
		} else {
			return $items["subfield"];
		}
		return $results;
	}

	private function callnumbertest($items)
	{

		$base = isset($items["subfield"][2]) ? (string)$items["subfield"][2] : '';
		$base2 = isset($items["subfield"][3]) ? (string)$items["subfield"][3] : '';
		return $base . " " . $base2;
	}


	private function callnumbernormalized($items)
	{
		return $this->normalize($items);
	}

	private function info($items)
	{
		$results = '';
		if(is_array($items["subfield"])) {
			foreach($items["subfield"] as $subfield) {
				$results .= (string)$subfield;
			}
		} else {
			return $items["subfield"];
		}
		return $results;
	}


	// private function search($url)
	// {
	//   	$client = new Client();
	//   	$location = 'http://fcaa.library.umass.edu/X?';
	// 	$response = $client->createRequest()
	// 		->setMethod('get')
	// 		->setFormat(Client::FORMAT_XML)
	// 		->setUrl($location . $url)
	// 		->send();
	// 	if ($response->isOk) {
	// 		return $response->data;
	// 	}
	// }

	private function searchDewey($call_number, $location)
	{
		$model = new Dewey();
		$provider = Yii::$app->db->createCommand("
			SELECT shelf
			FROM `dewey`
			WHERE '$call_number' BETWEEN `call_number_begin` and `call_number_end` AND collection = '$location'
		")->queryAll();
		return $provider;
	}

	private function locationMap($location)
	{
		switch($location){
			case 'SNSTK':
				return 'Dewey';
			break;
			case 'STDEW';
				return "West Street Dewey";
			break;
			case 'SNREF':
				return "Neilson Reference";
			break;
			case 'SSNRE':
				return "Young Reference";
			break;
			case 'STJL':
				return "Josten Periodicals";
			break;
			case 'SNPER':
				return "Neilson Periodicals";
			break;
		}
	}

	private function normalize($callno) {
		$alpha_match = preg_match("/[A-Z]{1,3}/", $callno, $alpha_bit);
		$numeric_match = preg_match("/[0-9]{1,4}/", $callno, $numeric_bit);
		$decimal_match = preg_match("/[.][0-9]{0,3}/", $callno, $decimal_bit);
		$cutter1_match = preg_match("/\.[A-Z][0-9]{1,7}/", $callno, $cutter1_bit);
		$cutter2_match = preg_match("/(?<=[ ])[A-Z][0-9]{1,6}/", $callno, $cutter2_bit);
		$date_match = preg_match("/(?<=[ ])[0-9][0-9][0-9][0-9][a-zA-Z]?/", $callno, $date_bit);
	// Validates call number part; checks to see if it exists.  If not, returns null.
		if ($cutter1_match == "0"){$cutter1_bit = array('');}
		if ($cutter2_match == "0"){$cutter2_bit = array('');}
		if ($date_match == "0"){$date_bit = array('');}
	// Normalizes callnumber elements that need normalizing.
		$numeric_bit_f = str_pad($numeric_bit[0], 4, '0', STR_PAD_LEFT);
		$decimal_bit_f = str_pad($decimal_bit[0], 4, '0', STR_PAD_RIGHT);

	// Prints normalized callnumber.
		$display = 	"{$alpha_bit[0]} " .
					"{$numeric_bit_f}" .
					"{$decimal_bit_f} " .
					"{$cutter1_bit[0]} " .
					"{$cutter2_bit[0]} " .
					"{$date_bit[0]}";
		return $display;
	}




	private function format($value)
	{
		print '<pre>';
		print_r($value);
		print '</pre>';
	}


	private function ADMCheck($barcode)
	{
		switch(true){
			case strpos($barcode, '310212') === 0:
				return 'AMH50';
			break;
			case strpos($barcode, '310093') === 0:
				return 'HAM50';
			break;
			case strpos($barcode, '310203') === 0:
				return 'MHC50';
			break;
			case strpos($barcode, '310183') === 0:
				return 'SMT50';
			break;
			case strpos($barcode, '312066') === 0:
				return 'UMA50';
			break;
			case strpos($barcode, '314000') === 0:
				return 'DEP50';
			break;
			default:
				return 'DEP50';
			break;
		}

	}

}
