<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\Tray;
use app\models\User;

class TrayLogApiController extends ActiveController
{
    public $modelClass = 'app\models\TrayLog';

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

    public function actionSearch()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        $actionQ = isset($data['action']) ? $data['action'] : null;
        $barcodeQ = isset($data['barcode']) ? $data['barcode'] : null;
        $detailsQ = isset($data['details']) ? $data['details'] : null;
        $userQ = isset($data['user']) ? $data['user'] : null;
        $timestampPost = isset($data['timestampPost']) ? $data['timestampPost'] : null;
        $timestampAnte = isset($data['timestampAnte']) ? $data['timestampAnte'] : null;

        if ($tokenCheck['level'] >= 60) {
            $query = $this->modelClass::find()
                ->joinWith('tray', 'tray_log.tray_id = tray.id')
                ->joinWith('user', 'tray_log.user_id = user.id')
                ->andFilterWhere(['tray_log.action' => $actionQ])
                ->andFilterWhere(['like', 'tray.barcode', $barcodeQ])
                ->andFilterWhere(['like', 'tray_log.details', $detailsQ])
                ->andFilterWhere(['like', 'user.name', $userQ])
                ->andFilterWhere(['>=', 'tray_log.timestamp', $timestampPost])
                ->andFilterWhere(['<', 'tray_log.timestamp', $timestampAnte])
                ->orderBy(['tray_log.id' => SORT_DESC])
                ->limit(100)->all();
            return $query;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to view logs');
        }
    }

    public function actionActionsList()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 60) {
            $results = $this->modelClass::find()
                ->select('action')
                ->distinct()
                ->all();
            // Use map/reduce on results and just return a list of the action strings
            $actions = array_map(function($result) {
                return $result->action;
            }, $results);
            return $actions;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to view logs');
        }
    }

    public function actionBrowse()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (isset($data['barcode'])) {
            $tray_id = Tray::find()->where(['barcode' => $data['barcode']])->one()->id;
        }
        else {
            $tray_id = '';
        }
        $action = isset($data['action']) ? $data['action'] : null;
        $details = isset($data['details']) ? $data['details'] : '';

        if ($tokenCheck['level'] >= 60) {
            // If a barcode has been provided, search by barcode and return
            // a liminted number of results
            $provider = new ActiveDataProvider([
                'query' => $this->modelClass::find()
                    ->filterWhere(['tray_id' => $tray_id])
                    ->andFilterWhere(['action' => $action])
                    ->andWhere(['like', 'details', $details]),
                'sort' => [
                    'defaultOrder' => [
                        'timestamp' => SORT_DESC,
                    ]
                ],
                'pagination' => [
                    'pageSize' => 20,
                ],
            ]);
            return $provider->getModels();
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to view logs');
        }
    }

    public function actionAddedStatistics()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 35) {
            // Get data by date and user
            $data = array();
            // TODO: make this a setting in the database
            $startDate = strtotime("previous week Monday");
            $numberOfDays = round((strtotime("now") - $startDate) / (60 * 60 * 24));

            for ($count = 0; $count < $numberOfDays; $count++) {
                $date = date('Y-m-d', strtotime('-' . $count . ' days'));
                $queryCommand = $this->modelClass::find()
                    ->select('user.name, count(*) as count')
                    ->joinWith('user', 'tray_log.user_id = user.id')
                    ->where(['tray_log.action' => 'Added'])
                    ->andWhere(['like', 'tray_log.timestamp', $date])
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
        else {
            throw new \yii\web\HttpException(403, "You do not have permission to view this data.");
        }
    }


    public function actionUpdatedStatistics()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 35) {
            // Get data by date and user
            $data = array();
            // TODO: make this a setting in the database
            $startDate = strtotime("previous week Monday");
            $numberOfDays = round((strtotime("now") - $startDate) / (60 * 60 * 24));
            $omitTemporaryShelves = [
                'AND',
                ['not like', 'tray_log.details', 'shelf 00'],
                ['not like', 'tray_log.details', 'shelf BR'],
                ['not like', 'tray_log.details', 'shelf 99'],
            ];

            for ($count = 0; $count < $numberOfDays; $count++) {
                $date = date('Y-m-d', strtotime('-' . $count . ' days'));
                $queryCommand = $this->modelClass::find()
                    ->select('user.name, count(*) as count')
                    ->joinWith('user', 'tray_log.user_id = user.id')
                    ->where(['tray_log.action' => 'Updated'])
                    ->andWhere(['like', 'tray_log.timestamp', $date])
                    ->andWhere($omitTemporaryShelves)
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
        else {
            throw new \yii\web\HttpException(403, "You do not have permission to view this data.");
        }
    }

}

