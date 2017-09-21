<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\httpclient\XmlParser;
use yii\httpclient\Client;
use app\models\BarcodeTray;
use app\models\TrayShelf;


class Aleph extends Model
{	
	public $barcode;
	
	public function processTray($tray)
	{
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		$items = $this->getTrayByID($tray);
		$set = array();
		foreach($items as $item){
			$set[] = $this->processBarcode($item['barcode'], 'SMT50');
		}
		return $set;
	}
	
	public function processPagingSlips($day)
	{
		$date = date("Ymd");	
		if($day == "morning"){
			$paging = file("http://fcaw.library.umass.edu/pickup/anx.cir.12.paging". $date ."_am_barcode");
		} else {
			$paging = file("http://fcaw.library.umass.edu/pickup/anx.cir.12.paging". $date ."_pm_barcode");
		}	
		$set = array();
		foreach($paging as $items){
			$items = str_replace('<z30-barcode>', '', $items);
			$items = str_replace('</z30-barcode>', '', $items);
			$set[] = $items;
		}
		$data = array();
		foreach($set as $list){
			$data[] = $this->processBarcode($list, 'SMT50');
		}
		return($data);
	}
	
	public function processShelfTray($tray)
	{
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		$items = $this->getTrayByBarcode($tray);
		$set = array();
		foreach($items as $item){
			$set[] = $this->processBarcode($item['barcode'], 'SMT50');
		}
		return $set;
	}
	
	
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
    
    public function processBarcode($barcode, $library)
    {
	    \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
	    $this->barcode = $barcode;
	    $options = array (
		    'op' => 'read-item',
		    'library' => $library,
		    'item_barcode' => trim($barcode)
	    );
	    $url = http_build_query($options, '', '&');
	    $search = $this->search($url);
	    if(isset($search["z30"]['z30-doc-number'])) {
	    	return $this->processRequest($search["z30"]['z30-doc-number'], $library);
	    } else {
		    return false;
	    }	
    }
    
    private function processRequest($request, $library)
    {
	   	$options = array (
			'op' => 'find_doc',
			'base' => $library,
			'doc-number' => $request
		);
		$url = http_build_query($options, '' , '&');	
		$search = $this->search($url);
		$term = '';
		foreach ($search["record"]["metadata"]["oai_marc"]["varfield"] as $field) 
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
	    return $this->getDoc(str_replace('ADMFCL01', '', $term));
    }
    
    public function processTitleSearch($request)
    {
	    \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
	   	$options = array (
			'op' => 'find',
			'base' => 'FCL01SMT',
			'request' => "WRD=$request AND WSB=SCANN NOT WTY=E-Book" 
		);
		$url = http_build_query($options, '' , '&');	
		$search = $this->search($url);
	    return array_filter($this->getRecords($search['set_number'])); 
    }
    
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
    
    
    private function getDoc($id)
    {
	  	$options = array (
			'op' => 'find-doc',
			'base' => 'FCL01',
			'doc_num' => $id
		);
		$url = http_build_query($options, '' , '&');	
		$search = $this->search($url);  
		return $this->getInfo($search["record"]["metadata"]["oai_marc"]); 
    }
    
    private function getInfo($set)
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
	    
		foreach($set["varfield"] as $items) {
			switch($items["@attributes"]["id"]){
				case '020':
					$results['isbn'] = $this->is($items);
				break;
				case '022':
					$results["issn"] = $this->is($items);
				break;	 
/*
				case '050':
			   	case '060':
			   	case '070':
			   	case '090':
			   	case '092':
			   	case '099':
			   		$results["call_number"] = $this->callnumber($items);
// 			   		$results["call_number_normalized"] = $this->callnumbernormalized($results["call_number"]);	   		
			   	break;
*/
				case '245':
					$results["title"] = $this->title($items);
				break;	
				case '300':
					$results["description"] = $this->info($items);
				break;
				case '852':
					$results['barcode'] = $this->record_barcode($items);
					$results["call_number"] = $this->callnumbertest($items);
					$results["call_number_normalized"] = $this->callnumbernormalized($results["call_number"]);
				break;
				case '952':
					$results["old_location"] = $this->old_location($items);
				break;			
			}
		}
		if(isset($this->barcode)){
			$barcode = $this->barcode;
		} else {
			$barcode = $results['barcode'];
		}	
				
		$tray = $this->getTray($barcode);
		$shelf = $this->getShelf($tray["boxbarcode"]);
		$results["tray_id"] = isset($tray["id"]) ? $tray["id"] : '';
		$results["tray_barcode"] = isset($tray["boxbarcode"]) ? $tray["boxbarcode"] : '';
		$results["stream"] = isset($tray["stream"]) ? $tray["stream"] : '';
		$results["status"] = isset($tray["status"]) ? $tray["status"] : '';
		$results["shelf_barcode"] = isset($shelf["shelf"]) ? $shelf["shelf"] : '' ;
		$results["shelf"] = isset($shelf) ? $shelf : '';
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
		return BarcodeTray::find()->where(['barcode' => trim($barcode)])->one();		    
    }
    
    private function getShelf($tray)
    {
	    return TrayShelf::find()->where(['boxbarcode' => $tray])->one();
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
	    if(isset($items["subfield"][4])) {
	    	return (string)$items["subfield"][4];
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
		return $base . $base2;		    
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
    
    
    private function search($url)
    {
	  	$client = new Client();
	  	$location = 'http://fcaa.library.umass.edu/X?';
		$response = $client->createRequest()
			->setMethod('get')
			->setFormat(Client::FORMAT_XML)
			->setUrl($location . $url)
			->send();
		if ($response->isOk) {
			return $response->data;
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
    
    
}
