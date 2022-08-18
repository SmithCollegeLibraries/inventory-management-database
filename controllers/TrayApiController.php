<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use app\models\Aleph;
use app\models\BarcodeTray;
use yii\filters\auth\QueryParamAuth;
use app\models\FOLIO;

class TrayApiController extends ActiveController
{
	public $modelClass = 'app\models\BarcodeTray';
	
	public function init()
	{
   		parent::init();
   		\Yii::$app->user->enableSession = false;
	}
	
	public function behaviors()
	{
    	$behaviors = parent::behaviors();
		$behaviors['authenticator'] = [
        	'class' => QueryParamAuth::className(),
			];
		return $behaviors;
	}
	
	public function actionBarcodeInsert()
	{
		$model = new $this->modelClass;
		$json = file_get_contents('php://input');
		$data = json_decode($json, true);
		if(is_array($data["barcodes"])) {
			$barcode = explode(PHP_EOL, $data['barcodes'][0]);
		} else {
			$barcode = explode(PHP_EOL, $data['barcodes']);	
		}
		$barcode = array_filter($barcode);
		$created_date = date("Y-m-d H:i:s");
		$verify = $this->verify($barcode);
		if($verify === true) 
		{
			foreach($barcode as $barcodes) 
			{
				Yii::$app->db->createCommand()->insert('barcode_tray', [
						'boxbarcode' => filter_var(trim($data["boxbarcode"]), FILTER_SANITIZE_NUMBER_INT),
						'barcode' => trim($barcodes),
						'stream' => trim($data["location"]),
						'initials' => 'SYS',
						'added' => 	$data["added"],
						'timestamp' => $created_date
					])->execute();
			}
			return \yii\helpers\Json::encode(true);
		} else {
			return $verify;
		}	
	}
	
	public function actionSearchTrayId()
	{
	  return BarcodeTray::find()->where(['like', 'boxbarcode', $_GET["query"]])->limit(50)->all();  
	}
	
	public function actionSearchBarcode()
	{
		$barcode = Yii::$app->request->get('barcode');
		// $aleph = new Aleph();
		$folio = new FOLIO();
		$set = array();
		if (strpos($barcode, ',') !== false) {
			$barcodes = explode(',', $barcode);
		} else {
			$barcodes = explode(PHP_EOL, $barcode);
		}	
		foreach($barcodes as $items){
			$set[] = $folio->processBarcode($items, 'SMT50');
		}
		return $set;
	}
	
	public function actionPagingSlips()
	{
		$folio = new FOLIO();
		return $folio->processPagingSlips();
		// $aleph = new Aleph();
		// return $aleph->processPagingSlips($_GET["day"]);
	}

	
	public function actionSearchTray()
	{
		$barcode = Yii::$app->request->get('query');
		// $aleph = new Aleph();
		$folio = new FOLIO();
		return $folio->processTray($barcode);		
	}
	
	//Still needs to be updated
	public function actionSearchTitle()
	{
		$title = Yii::$app->request->get('query');
		$folio = new FOLIO();
		// $aleph = new Aleph();
		return $folio->processTitleSearch($title);	
	}

	public function actionSearchOclc()
	{
		$oclc = Yii::$app->request->get('query');
		// $aleph = new Aleph();
		$folio = new FOLIO();
		return $folio->processByOCLC($oclc);		
	}
	
	public function actionSearchCall()
	{
		$call = Yii::$app->request->get('query');
		$folio = new FOLIO();
		// $aleph = new Aleph();
		return $folio->processCallNumber($call);
	}
	
	public function actionSearchMultiCall()
	{
		$call = Yii::$app->request->get('query');
		$folio = new FOLIO();
		// $aleph = new Aleph();
		return $folio->processMultiCallNumber($call);
	}
	
	private function verify($barcodes) 
	{	 
       	$model = new $this->modelClass;
		try {
            $provider = new ActiveDataProvider([
                'query' => $model->find()
                	->select('*')
                	->andFilterWhere(['in', 'barcode', $barcodes])
                	->asArray(),
					'pagination' => false
				]);
        } catch (Exception $ex) {
            throw new \yii\web\HttpException(500, 'Internal server error');
        }

		if ($provider->getCount() <= 0) {
            return true;
        } else {
            return $provider;
        }
   	}
   	
}
