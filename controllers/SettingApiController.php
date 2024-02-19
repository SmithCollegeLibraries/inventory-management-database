<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\Setting;

class SettingApiController extends ActiveController
{
    public $modelClass = 'app\models\Setting';

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

    public function actionGetSetting()
    {
        $name = $_REQUEST["name"];
        $setting = Setting::find()->where(['name' => $name])->one();
        return $setting;
    }

    public function actionGetAllSettings()
    {
        $settingsDict = [];
        $settings = Setting::find()->all();
        foreach ($settings as $setting) {
            $processedValue = is_numeric($setting->value) ? intval($setting->value) : $setting->value;
            $settingsDict[$setting->name] = $processedValue;
        }
        return $settingsDict;
    }
}
