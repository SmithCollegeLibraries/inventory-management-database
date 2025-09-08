<?php

namespace app\controllers;

use Yii;
use yii\db\Expression;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\Item;
use app\models\User;

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

    public function actionSearch($limit = 100, $download = false)
    {
        $token = $_REQUEST["access-token"] ?? null;
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if (!$tokenCheck) {
            throw new \yii\web\ForbiddenHttpException('Invalid access token');
        }
        else if ($tokenCheck['level'] < 40) {
            throw new \yii\web\HttpException(403, 'You do not have permission to view logs');
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true) ?? [];

        $actionQ = $data['action'] ?? null;
        $barcodeQ = $data['barcode'] ?? null;
        $detailsQ = $data['details'] ?? null;
        $userQ = $data['user'] ?? null;
        $flagQ = $data['flag'] ?? null;
        $timestampPost = $data['timestampPost'] ?? null;
        $timestampAnte = $data['timestampAnte'] ?? null;

        $query = (new \yii\db\Query())
            ->select([
                'item_log.id',
                'item.barcode',
                'item.flag',
                'item_log.action',
                'user.name AS user',
                'item_log.details',
                'item_log.timestamp',
            ])
            ->from('item_log')
            ->leftJoin('item', 'item.id = item_log.item_id')
            ->leftJoin('user', 'user.id = item_log.user_id')
            ->andFilterWhere(['item_log.action' => $actionQ])
            ->andFilterWhere(['item.barcode' => $barcodeQ])
            ->andFilterWhere(['like', 'user.name', $userQ])
            ->andFilterWhere(['like', 'item_log.details', $detailsQ])
            ->andFilterWhere(['>=', 'item_log.timestamp', $timestampPost])
            ->andFilterWhere(['<', 'item_log.timestamp', $timestampAnte]);
            if ($flagQ) {
                $query->andWhere(['item.flag' => $flagQ]);
            }
            $query->orderBy(['item_log.timestamp' => SORT_DESC]);

        if ($download) {
            $db = Yii::$app->db;
            $db->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

            if (ob_get_level()) {
                ob_end_clean(); // clear output buffers
            }

            $filename = 'sis-item-log-' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Cache-Control: no-store');

            $reader = $query->createCommand()->query();
            $output = fopen('php://output', 'w');

            // CSV header
            fputcsv(
                $output,
                ['ID', 'Item', 'Flag', 'Action', 'User', 'Details', 'Timestamp'],
                ',', '"', '\\',
            );

            foreach ($reader as $row) {
                fputcsv(
                    $output,
                    [
                        $row['id'],
                        $row['barcode'],
                        $row['flag'],
                        $row['action'],
                        $row['user'],
                        $row['details'],
                        $row['timestamp'],
                    ],
                    ',', '"', '\\'
                );
                flush(); // send buffer immediately
            }

            fclose($output);
            $db->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            exit();
        }

        // Normal JSON response
        $rows = $query->limit($limit)->all();
        return $this->asJson($rows);
    }

    public function actionActionsList()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 40) {
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
            $item_id = Item::find()->where(['barcode' => $data['barcode']])->one()->id;
        }
        else {
            $item_id = '';
        }
        $action = isset($data['action']) ? $data['action'] : null;
        $details = isset($data['details']) ? $data['details'] : '';

        if ($tokenCheck['level'] >= 40) {
            // If a barcode has been provided, search by barcode and return
            // a liminted number of results
            $provider = new ActiveDataProvider([
                'query' => $this->modelClass::find()
                    ->filterWhere(['item_id' => $item_id])
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

    public function actionRequestHistory()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 40) {
            $requests = $this->modelClass::find()
                ->select([
                    'year' => 'YEAR(timestamp)',
                    'month' => 'MONTH(timestamp)',
                    'action',
                    'collection_code' => 'collection.code',
                    'count' => new Expression('COUNT(*)'),
                ])
                ->from('item_log')
                ->leftJoin('item', 'item_log.item_id = item.id')
                ->leftJoin('collection', 'item.collection_id = collection.id')
                ->where(['in', 'action', ['Picklist: Requested', 'Circulated', 'Marked missing']])
                ->groupBy(['year', 'month', 'action', 'collection_code'])
                ->orderBy(['year' => SORT_ASC, 'month' => SORT_ASC])
                ->asArray()
                ->all();
            return $requests;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to see request history');
        }
    }
}
