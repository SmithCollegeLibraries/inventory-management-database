<?php

namespace app\controllers;
use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use app\models\Aleph;
use app\models\InternalRequests;
use yii\filters\auth\QueryParamAuth;

class InternalRequestsController extends ActiveController
{
	public $modelClass = 'app\models\InternalRequests';
	
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
	
	
	public function actionStatus() 
	{	 
		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		return InternalRequests::find()
			->joinWith(['internalRequestsComments comments'])
			->orderBy('comments.timestamp')
			->andFilterWhere(['completed' => $_REQUEST['completed']])
			->asArray()
			->all();
   	}
   	

}
