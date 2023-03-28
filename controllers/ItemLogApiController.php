<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

$startDate = strtotime("2023-03-20");
$numberOfDays = round((strtotime("now") - $startDate) / (60 * 60 * 24));

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
        global $numberOfDays;

        for ($count = 0; $count < $numberOfDays; $count++) {
            $date = date('Y-m-d', strtotime('-' . $count . ' days'));
            $queryCommand = $this->modelClass::find()
                ->select('user.name, count(*) as count')
                ->joinWith('user', 'item_log.user_id = user.id')
                ->where(['item_log.action' => 'Added'])
                ->andWhere(['like', 'item_log.timestamp', $date])
                ->groupBy(['user.name'])
                ->createCommand();
            if ($queryCommand->queryAll() != []) {
                $data[] = [
                    "date" => $date,
                    "counts" => $queryCommand->queryAll()
                ];
            }
        }
        return $data;
    }
}
