<?php

namespace app\controllers;
use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use app\models\BarcodeTray;
use app\models\TrayShelf;
use app\models\Aleph;
use yii\filters\auth\QueryParamAuth;

class StatisticsController extends ActiveController
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
	
	public function actionDailyStatistics(){
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		$dailyItems = BarcodeTray::find()
			->select(['MONTH(timestamp) as month, DAY(timestamp) as day, YEAR(timestamp) as year, COUNT(id) AS count'])
			->andFilterWhere(['MONTH(timestamp)'=> isset($_REQUEST['month']) ? $_REQUEST['month'] : date('n')])
			->andFilterWhere(['YEAR(timestamp)'=> isset($_REQUEST['year']) ? $_REQUEST['year'] : date('Y')])
			->andFilterWhere(['DAY(timestamp)'=> isset($_REQUEST['day']) ? $_REQUEST['day'] : date('j')])
			->asArray()
			->all();
		
		$dailyShelf = TrayShelf::find()
			->select(['MONTH(timestamp) as month, DAY(timestamp) as day, YEAR(timestamp) as year, COUNT(id) AS count'])
			->andFilterWhere(['MONTH(timestamp)'=> isset($_REQUEST['month']) ? $_REQUEST['month'] : date('n')])
			->andFilterWhere(['YEAR(timestamp)'=> isset($_REQUEST['year']) ? $_REQUEST['year'] : date('Y')])
			->andFilterWhere(['DAY(timestamp)'=> isset($_REQUEST['day']) ? $_REQUEST['day'] : date('j')])
			->asArray()
			->all();	
			
		$dailyOffCampus = BarcodeTray::find()
			->select(['MONTH(timestamp) as month, DAY(timestamp) as day, YEAR(timestamp) as year, stream as collection, status, COUNT(id) AS count'])
			->andFilterWhere(['status' => 'Off Campus'])
			->andFilterWhere(['MONTH(timestamp)'=> isset($_REQUEST['month']) ? $_REQUEST['month'] : date('n')])
			->andFilterWhere(['YEAR(timestamp)'=> isset($_REQUEST['year']) ? $_REQUEST['year'] : date('Y')])
			->andFilterWhere(['DAY(timestamp)'=> isset($_REQUEST['day']) ? $_REQUEST['day'] : date('j')])
			->groupBy('stream')
			->asArray()
			->all();
		
		$dailyMissing = BarcodeTray::find()
			->select(['MONTH(timestamp) as month, DAY(timestamp) as day, YEAR(timestamp) as year, stream as collection, status, COUNT(id) AS count'])
			->andFilterWhere(['status' => 'Missing'])
			->andFilterWhere(['MONTH(timestamp)'=> isset($_REQUEST['month']) ? $_REQUEST['month'] : date('n')])
			->andFilterWhere(['YEAR(timestamp)'=> isset($_REQUEST['year']) ? $_REQUEST['year'] : date('Y')])
			->andFilterWhere(['DAY(timestamp)'=> isset($_REQUEST['day']) ? $_REQUEST['day'] : date('j')])
			->groupBy('stream')
			->asArray()
			->all();			
		
		return $statistics = [
			'today' => date('n') . '/' . date('j') . '/' . date('y'),
			'items' => $dailyItems,
			'shelf' => $dailyShelf,
			'offcampus' => $dailyOffCampus,
			'missing' => $dailyMissing
		];
	}
	
	public function actionFindItemsByMonth(){
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		return BarcodeTray::find()
			->select(['MONTH(timestamp) as month, YEAR(timestamp) as year, COUNT(id) AS count'])
			->andFilterWhere(['MONTH(timestamp)'=> $_REQUEST['month']])
			->andFilterWhere(['YEAR(timestamp)'=> $_REQUEST['year']])
			->asArray()
			->all();	
	}
	
	public function actionFindItemsByYear(){
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		return BarcodeTray::find()
			->select(['YEAR(timestamp) as year, COUNT(id) AS count'])
			->andFilterWhere(['YEAR(timestamp)'=> isset($_REQUEST['year']) ? $_REQUEST['year'] : 2018])
			->groupBy('YEAR')
			->asArray()
			->all();	
	}
	
	public function actionFindByCollection(){
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		return BarcodeTray::find()
			->select(['stream as collection, COUNT(id) AS count', 'YEAR(timestamp) as year'])
			->andFilterWhere(['stream' => $_REQUEST['collection']])
			->andFilterWhere(isset($_REQUEST['year']) ? ['YEAR(timestamp)'=> $_REQUEST['year']] : ['YEAR(timestamp)'=> '2018'])
			->asArray()
			->all();	
	}
	
	public function actionFindAllCollections(){
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		return BarcodeTray::find()
			->select(['stream as collection, COUNT(id) AS count', 'YEAR(timestamp) as year'])
			->groupBy('stream', 'YEAR(timestamp)')
			->orderBy(['YEAR' => 'ASC'])
			->asArray()
			->all();	
	}
	
	
	public function actionFindAllItems(){
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		return BarcodeTray::find()
			->select(['MONTH(timestamp) as month, YEAR(timestamp) as year, DAY(timestamp) as day, COUNT(barcode) AS count'])
			->groupBy(['YEAR', 'MONTH'])
			->orderBy(['YEAR' => 'ASC', 'MONTH' => 'ASC'])
			->asArray()
			->all();	
	}
	
	public function actionFindShelfByMonth(){
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		return TrayShelf::find()
			->select(['MONTH(timestamp) as month, YEAR(timestamp) as year, COUNT(id) AS count'])
			->andFilterWhere(['MONTH(timestamp)'=> $_REQUEST['month']])
			->andFilterWhere(['YEAR(timestamp)'=> isset($_REQUEST['year']) ? $_REQUEST['year'] : 2018])
			->asArray()
			->all();	
	}
	
	public function actionFindShelfByYear(){
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		return TrayShelf::find()
			->select(['YEAR(timestamp) as year, COUNT(id) AS count'])
			->andFilterWhere(['YEAR(timestamp)'=> isset($_REQUEST['year']) ? $_REQUEST['year'] : 2018])
			->groupBy('YEAR')
			->asArray()
			->all();	
	}
	
	public function actionFindAllShelf(){
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		return TrayShelf::find()
			->select(['MONTH(timestamp) as month, YEAR(timestamp) as year, COUNT(id) AS count'])
			->groupBy(['YEAR', 'MONTH'])
			->orderBy(['YEAR' => 'ASC', 'MONTH' => 'ASC'])
			->asArray()
			->all();	
	}
	
	public function actionFindByStatus(){
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		return BarcodeTray::find()
			->select(['stream as collection, status, COUNT(id) AS count'])
			->groupBy('stream')
			->asArray()
			->all();	
	}
	
	public function actionMissingReport(){
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		return BarcodeTray::find()
			->select(['id', 'boxbarcode', 'barcode', 'stream as collection', 'status', 'added', 'timestamp'])
			->andFilterWhere(['status' => 'Missing'])
			->asArray()
			->all();
	}
	
}


