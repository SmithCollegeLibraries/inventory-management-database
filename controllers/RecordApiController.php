<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;


class RecordApiController extends ActiveController
{
	public $modelClass = 'app\models\RecordInformation';
	
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
}
