<?php

namespace app\controllers;
use yii\rest\ActiveController;
use yii\filters\auth\QueryParamAuth;
use app\models\History;

class HistoryController extends ActiveController
{
	public $modelClass = 'app\models\History';
	
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
	
	public function actions()
    {
        $actions = parent::actions();
        unset($actions['index']);
        return $actions;
    }
	
	public function actionIndex()
	{
		$date = date("Y-m-d");
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		return History::find()
			->select('*')
			->andFilterWhere(['like', 'timestamp', $date])
			->asArray()
			->all();	
	}
	
	public function actionSearch()
	{
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		return History::find()
			->select('*')
			->orFilterWhere(['like', 'action', $_REQUEST['query']])
			->orFilterWhere(['like', 'item', $_REQUEST['query']])
			->orFilterWhere(['like', 'status_change', $_REQUEST['query']])
			->orFilterWhere(['like', 'timestamp', $_REQUEST['query']])
			->asArray()
			->all();
	}
}
