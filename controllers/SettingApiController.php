<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\Setting;
use app\models\User;

class SettingApiController extends ActiveController
{
    public $modelClass = 'app\models\Setting';
    public $modelLogClass = 'app\models\SettingLog';

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
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 10) {
            $name = $_REQUEST["name"];
            $setting = Setting::find()->where(['name' => $name])->one();
            return $setting;
        }
        else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to view settings');
        }
    }

    public function actionGetAllSettings()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 10) {
            $settingsDict = [];
            $settings = Setting::find()->all();
            foreach ($settings as $setting) {
                $processedValue = is_numeric($setting->value) ? intval($setting->value) : $setting->value;
                $settingsDict[$setting->name] = $processedValue;
            }
            return $settingsDict;
        }
        else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to view settings');
        }
    }

    public function actionNewSetting()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 100) {
            $name = $data["name"];
            $value = isset($data["value"]) ? $data["value"] : null;
            $setting = new Setting();
            $setting->name = $name;
            $setting->value = $value;
            $setting->save();

            // Log the creation of the setting
            $settingLog = new $this->modelLogClass();
            $settingLog->setting_id = $setting->id;
            $settingLog->value = $value;
            $settingLog->user_id = $tokenCheck->id;
            $settingLog->save();

            return $setting;
        }
        else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to create a new setting');
        }
    }

    public function actionUpdateSetting()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 80) {
            $name = $_REQUEST["name"];
            $value = $_REQUEST["value"];
            $setting = Setting::find()->where(['name' => $name])->one();
            if ($setting) {
                $setting->value = $value;
                $setting->save();

                $settingLog = new $this->modelLogClass();
                $settingLog->setting_id = $setting->id;
                $settingLog->value = $value;
                $settingLog->user_id = $tokenCheck->id;
                $settingLog->save();

                return $setting;
            }
            else {
                throw new \yii\web\NotFoundHttpException('Setting not found');
            }
        }
        else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to update settings');
        }
    }
}
