<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\Setting;
use app\models\User;

class SettingLogApiController extends ActiveController
{
    public $modelClass = 'app\models\SettingLog';

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

        $nameQ = $data['name'] ?? null;
        $userQ = $data['user'] ?? null;
        $timestampPost = $data['timestampPost'] ?? null;
        $timestampAnte = $data['timestampAnte'] ?? null;

        $query = (new \yii\db\Query())
            ->select([
                'setting_log.id',
                'setting.name',
                'setting.value',
                'user.name AS user',
                'setting_log.timestamp',
            ])
            ->from('setting_log')
            ->leftJoin('setting', 'setting.id = setting_log.setting_id')
            ->leftJoin('user', 'user.id = setting_log.user_id')
            ->andFilterWhere(['like', 'setting.name', $nameQ])
            ->andFilterWhere(['like', 'user.name', $userQ])
            ->andFilterWhere(['>=', 'setting_log.timestamp', $timestampPost])
            ->andFilterWhere(['<', 'setting_log.timestamp', $timestampAnte])
            ->orderBy(['setting_log.timestamp' => SORT_DESC]);

        if ($download) {
            $db = Yii::$app->db;
            $db->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

            if (ob_get_level()) {
                ob_end_clean(); // clear output buffers
            }

            $filename = 'sis-setting-log-' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Cache-Control: no-store');

            $reader = $query->createCommand()->query();
            $output = fopen('php://output', 'w');

            // CSV header
            fputcsv(
                $output,
                ['ID', 'Setting', 'Value', 'User', 'Timestamp'],
                ',', '"', '\\'
            );

            foreach ($reader as $row) {
                fputcsv(
                    $output,
                    [
                        $row['id'],
                        $row['name'],
                        $row['value'],
                        $row['user'],
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
        return $rows;
    }

}
