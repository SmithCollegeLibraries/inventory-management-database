<?php

namespace app\controllers;

use Yii;
use yii\db\Expression;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\User;
use app\commands\CacheTableController;


class FillRateApiController extends ActiveController
{
    public $modelClass = 'app\models\CacheFillRate';

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

    // The public access point to get all the fill rates for the past
    // $months months. The function will update the cache if necessary,
    // and then return the results from the cache tables. The date of the
    // last time this function was run is saved in the Settings table. All
    // months since that time, including the month of the last call of
    // this function, will be updated.
    public function actionGetFillRates(?int $months=null)
    {
        // Restrict to level 60 or more
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] < 60) {
            throw new \yii\web\HttpException(403, 'You do not have permission to view logs');
        }
        else {
            // $command = 'yii cache-tables/fill-rates ' . $tokenCheck['id'];
            CacheTableController::actionFillRates($tokenCheck['id']);

            // Then, return the results from the cache tables. If $months is
            // defined, return the fill rates for the past $months months;
            // otherwise, return results from all time.
            if ($months) {
                $startOfTotals = date('Y-m-01', strtotime("-$months months"));
                $fillRates = $this->modelClass::find()
                    ->where(['>=', new Expression("CONCAT(year, '-', LPAD(month, 2, '0'), '-01')"), $startOfTotals])
                    ->orderBy(['year' => SORT_DESC, 'month' => SORT_DESC])
                    ->all();
                return $fillRates;
            }
            else {
                $fillRates = $this->modelClass::find()
                    ->orderBy(['year' => SORT_DESC, 'month' => SORT_DESC])
                    ->all();
                return $fillRates;
            }
        }
    }
}
