<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

class ItemLogApiController extends ActiveController
{
    public $modelClass = 'app\models\ItemLog';

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
        $dataProvider = new ActiveDataProvider([
            'query' => $this->modelClass::find(),
            'pagination' => false,
        ]);
        return $dataProvider;
    }

    public function actionStatistics()
    {
        // Get data by date and user
        $data = array();
        $startDate = '2023-03-20';
        for ($date = date('Y-m-d'); $date >= $startDate; date('Y-m-d', strtotime($date . ' -1 day'))) {
            $query = $this->modelClass::find()
                ->select('user.name, count(*) as count')
                ->joinWith('user', 'item_log.user_id = user.id')
                ->where(['item_log.action' => 'Added'])
                ->andWhere(['like', 'item_log.timestamp', $date . '%'])
                ->groupBy(['user.name'])
                ->all();
            if ($query) {
                $data[] = $query;
            }
        }
        return $data;
    }

}

