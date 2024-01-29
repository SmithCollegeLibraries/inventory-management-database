<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\Collection;
use app\models\Tray;
use app\models\User;
use app\models\OldBarcodeTray;



class OldBarcodeTrayApiController extends ActiveController
{
    public $modelClass = 'app\models\OldBarcodeTray';

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

    // Check agaisnt FOLIO and update accordingly
    private function handleCheckFolio($barcode)
    {
        $results = \app\components\Folio::fullLookup($barcode);
        try {
            $barcodeInFolio = $results["data"]["totalRecords"] > 0;
            if ($barcodeInFolio) {
                return $this->handleBarcodeTrayUpdate($barcode, "Outstanding");
            }
            else {
                return $this->handleBarcodeTrayUpdate($barcode, "Old barcode");
            }
        }
        catch (\Exception $e) {
            return false;
        }
    }

    private function handleBarcodeTrayUpdate($barcode, $status)
    {
        $barcodeTray = $this->modelClass::find()->where(['barcode' => $barcode])->one();

        // If the item doesn't exist, do nothing
        if ($barcodeTray == null) {
            return array(
                "barcode" => $barcode,
                "status" => null,
            );
        }
        // Don't do anything if it's already marked as retrayed
        else if ($barcodeTray->status == "Retrayed") {
            return array(
                "barcode" => $barcode,
                "status" => "Retrayed",
            );
        }
        else {
            $barcodeTray->status = $status;
            $barcodeTray->save();
            return array(
                "barcode" => $barcode,
                "status" => $status,
            );
        }
    }

    public function actionBulkCheck()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        // Data should be in the form:
        // {
        //     "barcodes": ["3101...", ...]

        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 100) {
            try {
                $updated = [];
                for ($i = 0; $i < count($data["barcodes"]); $i++) {
                    $oldBarcodeTray = $this->modelClass::find()->where(['barcode' => $data["barcodes"][$i]])->one();
                    $barcode = $oldBarcodeTray->barcode;
                    $handleResults = $this->handleCheckFolio($barcode);
                    array_push($updated, $handleResults);
                }
                return $updated;
            }
            catch (Exception $e) {
                throw new \yii\web\HttpException(400, 'Error updating old barcode-trays.');
            }
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to bulk edit old barcode-trays.');
        }
    }
}

