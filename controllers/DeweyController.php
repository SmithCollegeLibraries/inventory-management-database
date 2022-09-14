<?php

namespace app\controllers;
use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use app\models\Aleph;

class DeweyController extends ActiveController
{
	public $modelClass = 'app\models\Dewey';
	
    public function actionSearch()
    {
	    \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
       	$barcode = Yii::$app->request->get('barcode');
		$aleph = new Aleph();
		$items = $aleph->processBarcode($barcode, 'SMT50');
		$search = $this->searchDewey($items["call_number"], $this->locationMap($items["old_location"]));
		return $search;
        
    }
    
    public function locationMap($location)
    {
	    switch($location){
		    case 'SNSTK':
		    	return 'Dewey';
		    break;	
		    case 'SNPER':
		    	return 'Neilson Periodicals';	
		    break;
		    case 'STDEW':
		    	return 'West Street Dewey';
		    break;
	    }
    }
    
    public function searchDewey($call_number, $location)
    {
	    $model = new $this->modelClass;
		$provider = Yii::$app->db->createCommand("
			SELECT * FROM `dewey` WHERE '$call_number' BETWEEN `call_number_begin` and `call_number_end` AND collection = '$location'")->queryOne();		
		return $provider;
    }

}
