<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

class CollectionApiController extends ActiveController
{
    public $modelClass = 'app\models\Collection';

    public function init()
    {
           parent::init();
           \Yii::$app->user->enableSession = false;
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => QueryParamAuth::class,
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
        $modelClass = 'app\models\Collection';
        $dataProvider = new ActiveDataProvider([
            'query' => $modelClass::find(),
            'pagination' => false,
        ]);
        return $dataProvider;
    }

}
