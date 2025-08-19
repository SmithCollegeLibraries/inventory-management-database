<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\Collection;
use app\models\User;

class CollectionLogApiController extends ActiveController
{
    public $modelClass = 'app\models\CollectionLog';

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
        $nameQ = $data['name'] ?? null;
        $detailsQ = $data['details'] ?? null;
        $userQ = $data['user'] ?? null;
        $timestampPost = $data['timestampPost'] ?? null;
        $timestampAnte = $data['timestampAnte'] ?? null;

        $query = (new \yii\db\Query())
            ->select([
                'collection_log.id',
                'collection.name',
                'collection_log.action',
                'user.name AS user',
                'collection_log.details',
                'collection_log.timestamp',
            ])
            ->from('collection_log')
            ->leftJoin('collection', 'collection.id = collection_log.collection_id')
            ->leftJoin('user', 'user.id = collection_log.user_id')
            ->andFilterWhere(['collection_log.action' => $actionQ])
            ->andFilterWhere(['like', 'collection.name', $nameQ])
            ->andFilterWhere(['like', 'user.name', $userQ])
            ->andFilterWhere(['like', 'collection_log.details', $detailsQ])
            ->andFilterWhere(['>=', 'collection_log.timestamp', $timestampPost])
            ->andFilterWhere(['<', 'collection_log.timestamp', $timestampAnte])
            ->orderBy(['collection_log.id' => SORT_DESC]);

        if ($download) {
            $db = Yii::$app->db;
            $db->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

            if (ob_get_level()) {
                ob_end_clean(); // clear output buffers
            }

            $filename = 'sis-collection-log-' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Cache-Control: no-store');

            $reader = $query->createCommand()->query();
            $output = fopen('php://output', 'w');

            // CSV header
            fputcsv(
                $output,
                ['ID', 'Collection', 'Action', 'User', 'Details', 'Timestamp'],
                ',', '"', '\\', "\n"
            );

            foreach ($reader as $row) {
                fputcsv(
                    $output,
                    [
                        $row['id'],
                        $row['name'],
                        $row['action'],
                        $row['user'],
                        $row['details'],
                        $row['timestamp'],
                    ],
                    ',', '"', '\\', "\n"
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

}
