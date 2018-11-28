<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use app\models\TrayShelf;
use app\models\Aleph;
use yii\filters\auth\QueryParamAuth;


class ShelfApiController extends ActiveController
{
	public $modelClass = 'app\models\TrayShelf';
	
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
	
    
    public function actionSearchShelfId()
	{
	  return TrayShelf::find()->where(['like', 'boxbarcode', $_GET["query"]])->one();  
	}
	
	public function actionSearchAllShelf()
	{
		 return TrayShelf::find()->where(['like', 'shelf', $_GET["query"]])->all();  
	}
	
	public function actionSearchShelf()
	{
		$shelf = Yii::$app->request->get('query');
		$aleph = new Aleph();
		return $aleph->processShelf($shelf);		
	}
   	
   	public function actionShelfInsert() 
   	{
	   	$model = new TrayShelf();
	   	$json = file_get_contents('php://input');
		$data = json_decode($json, true);
	   	
	   	$created_date = date("Y-m-d H:i:s");
	   	$boxbarcode = $data['boxbarcode'];
	   	$shelfbarcode = $data["shelfbarcode"];
	   	$row = substr($data["shelfbarcode"], 0, 2);
		$side = substr($data["shelfbarcode"], 2, 1);
		$ladder = substr($data["shelfbarcode"], 3, 2);
		$shelf_number = substr($data["shelfbarcode"], 5, 2);		
		$shelf_depth = $data['shelf_depth'];
		$shelf_position = $data['shelf_position'];
		$added = $data['added'];
		
	   	if ($shelfbarcode) {
			Yii::$app->db->createCommand()->insert('tray_shelf', [
					'boxbarcode' => filter_var(trim($boxbarcode), FILTER_SANITIZE_NUMBER_INT),
					'shelf' => trim($shelfbarcode),
					'row' => $row,
					'side' => $side,
					'ladder' => $ladder,
					'shelf_number' => $shelf_number,
					'shelf_depth' => $shelf_depth,
					'shelf_position' => $shelf_position,
					'initials' => 'SYS',
					'added' => $added,
					'timestamp' => $created_date
			])->execute();
			return \yii\helpers\Json::encode(true);
        } else {
			return \yii\helpers\Json::encode(false);
        }		
   	}
}
