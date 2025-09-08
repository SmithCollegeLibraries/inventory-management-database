<?php

namespace app\controllers;

use Yii;
use yii\db\Expression;
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
                'tray_log.id',
                'tray.barcode',
                'tray.flag',
                'tray_log.action',
                'user.name AS user',
                'tray_log.details',
                'tray_log.timestamp',
            ])
            ->from('tray_log')
            ->leftJoin('tray', 'tray.id = tray_log.tray_id')
            ->leftJoin('user', 'user.id = tray_log.user_id')
            ->andFilterWhere(['tray_log.action' => $actionQ])
            ->andFilterWhere(['tray.barcode' => $barcodeQ])
            ->andFilterWhere(['like', 'user.name', $userQ])
            ->andFilterWhere(['like', 'tray_log.details', $detailsQ])
            ->andFilterWhere(['>=', 'tray_log.timestamp', $timestampPost])
            ->andFilterWhere(['<', 'tray_log.timestamp', $timestampAnte]);
            if ($flagQ) {
                $query->andWhere(['tray.flag' => $flagQ]);
            }
            $query->orderBy(['tray_log.timestamp' => SORT_DESC]);

        if ($download) {
            $db = Yii::$app->db;
            $db->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

            if (ob_get_level()) {
                ob_end_clean(); // clear output buffers
            }

            $filename = 'sis-tray-log-' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Cache-Control: no-store');

            $reader = $query->createCommand()->query();
            $output = fopen('php://output', 'w');

            // CSV header
            fputcsv(
                $output,
                ['ID', 'Tray', 'Flag', 'Action', 'User', 'Details', 'Timestamp'],
                ',', '"', '\\'
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
        // Cast 'id' and 'flag' as int
        foreach ($rows as &$row) {
            if (isset($row['id'])) {
                $row['id'] = (int)$row['id'];
            }
            if (isset($row['flag'])) {
                $row['flag'] = (int)$row['flag'];
            }
        }
        return $rows;
    }

    public function actionActionsList()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 20) {
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

        if ($tokenCheck['level'] >= 40) {
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

}

