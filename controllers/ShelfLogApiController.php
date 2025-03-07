<?php

namespace app\controllers;

use Yii;
use yii\db\Expression;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\TrayLog;
use app\models\User;

class ShelfLogApiController extends ActiveController
{
    public $modelClass = 'app\models\ShelfLog';

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
                ->select([
                    'shelf_log.id',
                    'shelf.barcode',
                    'shelf_log.action',
                    'shelf_log.details',
                    'user.name AS user',
                    'shelf_log.timestamp',
                    'shelf_log.currentActive',
                    'shelf_log.currentFlag'
                ])
                ->joinWith('shelf', 'shelf_log.shelf_id = shelf.id')
                ->joinWith('user', 'shelf_log.user_id = user.id')
                ->andFilterWhere(['shelf_log.action' => $actionQ])
                ->andFilterWhere(['like', 'shelf.barcode', $barcodeQ])
                ->andFilterWhere(['like', 'shelf_log.details', $detailsQ])
                ->andFilterWhere(['like', 'user.name', $userQ])
                ->andFilterWhere(['>=', 'shelf_log.timestamp', $timestampPost])
                ->andFilterWhere(['<', 'shelf_log.timestamp', $timestampAnte])
                ->orderBy(['shelf_log.id' => SORT_DESC])
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

    public function actionFillRates()
    {
        $token = $_REQUEST["access-token"];
        $months = isset($_REQUEST["months"]) ? $_REQUEST["months"] : null;
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 60) {
            $collectionSizeCounts = TrayLog::find()
                ->select([
                    new Expression('YEAR(timestamp) AS year'),
                    new Expression('MONTH(timestamp) AS month'),
                    'size.code as size',
                    'collection.code as collection',
                    new Expression('COUNT(DISTINCT shelf.id) AS count'),
                ])
                ->leftJoin('tray', 'tray_log.tray_id = tray.id')
                ->leftJoin('shelf', 'tray.shelf_id = shelf.id')
                ->leftJoin('collection', 'tray.collection_id = collection.id')
                ->leftJoin('size', 'tray.size_id = size.id')
                ->where([
                    'tray_log.action' => 'Added',
                    'tray.active' => 1,
                ])
                ->andWhere(['tray.size_id' => new \yii\db\Expression('shelf.size_id')])
                ->andWhere(['tray.collection_id' => new \yii\db\Expression('shelf.collection_id')])
                ->andWhere([
                    'tray_log.timestamp' => new \yii\db\Expression(
                        '(SELECT MIN(tl2.timestamp)
                        FROM tray_log tl2
                        JOIN tray t2 ON tl2.tray_id = t2.id
                        WHERE t2.shelf_id = tray.shelf_id
                        AND tl2.action = "Added")'
                    )
                ])
                // Restrict to the last $months months, or all time if no
                // query parameter is given
                ->andWhere(['>=', 'timestamp', $months === null ? "0" : new Expression('DATE_FORMAT(DATE_SUB(NOW(), INTERVAL :months MONTH), "%Y-%m-01")', [':months' => $months])])
                ->groupBy(['year', 'month', 'tray.size_id', 'tray.collection_id'])
                ->asArray()
                // ->orderBy(['year' => SORT_ASC, 'month' => SORT_ASC, 'tray.size_id' => SORT_ASC, 'tray.collection_id' => SORT_ASC])
                ->all();
            return $collectionSizeCounts;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to view tray counts by collection and size.');
        }
    }

}
