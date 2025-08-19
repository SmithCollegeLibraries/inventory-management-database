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
        $timestampPost = $data['timestampPost'] ?? null;
        $timestampAnte = $data['timestampAnte'] ?? null;

        $query = (new \yii\db\Query())
            ->select([
                'shelf_log.id',
                'shelf.barcode',
                'shelf_log.action',
                'user.name AS user',
                'shelf_log.details',
                'shelf_log.timestamp',
            ])
            ->from('shelf_log')
            ->leftJoin('shelf', 'shelf.id = shelf_log.shelf_id')
            ->leftJoin('user', 'user.id = shelf_log.user_id')
            ->andFilterWhere(['shelf_log.action' => $actionQ])
            ->andFilterWhere(['shelf.barcode' => $barcodeQ])
            ->andFilterWhere(['like', 'user.name', $userQ])
            ->andFilterWhere(['like', 'shelf_log.details', $detailsQ])
            ->andFilterWhere(['>=', 'shelf_log.timestamp', $timestampPost])
            ->andFilterWhere(['<', 'shelf_log.timestamp', $timestampAnte])
            ->orderBy(['shelf_log.timestamp' => SORT_DESC]);

        if ($download) {
            $db = Yii::$app->db;
            $db->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

            if (ob_get_level()) {
                ob_end_clean(); // clear output buffers
            }

            $filename = 'sis-shelf-log-' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Cache-Control: no-store');

            $reader = $query->createCommand()->query();
            $output = fopen('php://output', 'w');

            // CSV header
            fputcsv(
                $output,
                ['ID', 'Shelf', 'Action', 'User', 'Details', 'Timestamp'],
                ',', '"', '\\', "\n"
            );

            foreach ($reader as $row) {
                fputcsv(
                    $output,
                    [
                        $row['id'],
                        $row['barcode'],
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
