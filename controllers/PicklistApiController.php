<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\User;

class PicklistApiController extends ActiveController
{
    public $modelClass = 'app\models\Picklist';

    static function truncate($string, $length)
    {
        return strlen($string) > $length ? mb_substr($string, 0, $length-1).'…' : $string;
    }

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

    public function actionGetPicklist()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 20) {
            $picklist = $this->modelClass::find()->all();
            return $picklist;
        }
        else {
            throw new \yii\web\HttpException(403, "You do not have permission to view the picklist.");
        }
    }

    function handleAddItems($barcodeList, $user, $notInSystemErrors=false)
    {
        $picklist = $this->modelClass::find()->all();
        $items = \app\models\Item::find()->where(['barcode' => $barcodeList])->all();

        // Throw an error if any of the barcodes provided aren't in the system
        if ($notInSystemErrors) {
            $barcodesInSystem = array_map(
                function($i) { return $i['barcode']; },
                $items
            );
            $barcodesNotInSystem = array_diff($barcodeList, $barcodesInSystem);
            if (count($barcodesNotInSystem) == 1) {
                throw new \yii\web\HttpException(400, "Barcode " . implode(", ", $barcodesNotInSystem) . " is not in the system");
            }
            else if (count($barcodesNotInSystem) >= 2) {
                throw new \yii\web\HttpException(400, "The following barcodes are not in the system: " . implode(", ", $barcodesNotInSystem));
            }
        }

        $picklistItemIds = array_map(
            function($p) { return $p['item_id']; },
            $picklist
        );

        $itemsNotInPicklist = array_filter(
            $items,
            function($i) use ($picklistItemIds) {
                return !in_array($i['id'], $picklistItemIds);
            }
        );

        // For items NOT already in the picklist:
        // Add items to the picklist table in batch
        Yii::$app->db->createCommand()->batchInsert(
            'picklist',
            ['item_id', 'user_id', 'title', 'volume'],
            array_map(
                function($i) {
                    $infoFromFolio = \app\components\Folio::getTitleAndVolume($i['barcode']);
                    $title = isset($infoFromFolio['title']) ? $this->truncate($infoFromFolio['title'], 79) : $i['barcode'];
                    $volume = isset($infoFromFolio['volume']) ? $this->truncate($infoFromFolio['volume'], 31) : null;
                    return [$i['id'], null, $title, $volume];
                },
                $itemsNotInPicklist
            )
        )->execute();
        // Add to the item log in batch
        Yii::$app->db->createCommand()->batchInsert(
            'item_log',
            ['item_id', 'user_id', 'action', 'details'],
            array_map(
                function($i) use ($user) {
                    return [$i['id'], $user['id'], 'Requested', sprintf('Item %s added to picklist', $i['barcode'])];
                },
                $itemsNotInPicklist
            )
        )->execute();

        // Return updated picklist
        return $this->modelClass::find()->all();
    }

    public function actionAddItems()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 40) {
            $newPicklist = $this->handleAddItems($data["barcodes"], $tokenCheck, true);
            return $newPicklist;
        }
        else {
            throw new \yii\web\HttpException(403, "You do not have permission to add items to the picklist.");
        }
    }

    public function actionAddFromFolio()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 40) {
            $barcodeList = \app\components\Folio::getPicklist("SC_ANNEX");
            $newPicklist = $this->handleAddItems($barcodeList, $tokenCheck, false);
            $existingBarcodes = array_map(
                function($i) { return $i['barcode']; },
                \app\models\Item::find()->where(['barcode' => $barcodeList])->asArray()->all()
            );
            $notInSystem = array_values(array_diff($barcodeList, $existingBarcodes));
            return ['newPicklist' => $newPicklist, 'notInSystem' => $notInSystem];
        }
        else {
            throw new \yii\web\HttpException(403, "You do not have permission to add items to the picklist.");
        }
    }

    // This function is used to assign items to a user in the picklist.
    // It also adds items to the picklist if they are not there already.
    public function actionAssignItems()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 40) {
            $picklist = $this->modelClass::find()->all();
            $items = \app\models\Item::find()->where(['barcode' => $data['barcodes']])->all();

            $picklistItemIdsExceptMine = array_map(
                function($p) { return $p['item_id']; },
                array_filter(
                    $picklist,
                    function($p) use ($tokenCheck) {
                        return $p['user_id'] != $tokenCheck['id'];
                    }
                )
            );
            $picklistItemIdsAssignedNotMine = array_map(
                function($p) { return $p['item_id']; },
                array_filter(
                    $picklist,
                    function($p) use ($tokenCheck) {
                        return $p['user_id'] != $tokenCheck['id'] && $p['user_id'] != null;
                    }
                )
            );
            $picklistItemIdsUnassigned = array_map(
                function($p) { return $p['item_id']; },
                array_filter(
                    $picklist,
                    function($p) use ($tokenCheck) {
                        return $p['user_id'] == null;
                    }
                )
            );
            $allPicklistItemIds = array_map(
                function($p) { return $p['item_id']; },
                $picklist
            );


            $itemsNotInPicklist = array_filter(
                $items,
                function($i) use ($allPicklistItemIds) {
                    return !in_array($i['id'], $allPicklistItemIds);
                }
            );
            $itemsInPicklistNotMine = array_filter(
                $items,
                function($i) use ($picklistItemIdsExceptMine) {
                    return in_array($i['id'], $picklistItemIdsExceptMine);
                }
            );
            $itemsAssignedInPicklistNotMine = array_filter(
                $items,
                function($i) use ($picklistItemIdsAssignedNotMine) {
                    return in_array($i['id'], $picklistItemIdsAssignedNotMine);
                }
            );
            $itemsUnassignedInPicklist = array_filter(
                $items,
                function($i) use ($picklistItemIdsUnassigned) {
                    return in_array($i['id'], $picklistItemIdsUnassigned);
                }
            );

            // For items NOT already in the picklist:
            // Add items to the picklist table in batch
            Yii::$app->db->createCommand()->batchInsert(
                'picklist',
                ['item_id', 'user_id', 'title', 'volume'],
                array_map(
                    function($i) use ($tokenCheck) {
                        $infoFromFolio = \app\components\Folio::getTitleAndVolume($i['barcode']);
                        $title = isset($infoFromFolio['title']) ? $this->truncate($infoFromFolio['title'], 79) : null;
                        $volume = isset($infoFromFolio['volume']) ? $this->truncate($infoFromFolio['volume'], 31) : null;
                        return [$i['id'], $tokenCheck['id'], $title, $volume];
                    },
                    $itemsNotInPicklist
                )
            )->execute();
            // Add to the item log in batch
            Yii::$app->db->createCommand()->batchInsert(
                'item_log',
                ['item_id', 'user_id', 'action', 'details'],
                array_map(
                    function($i) use ($tokenCheck) {
                        return [$i['id'], $tokenCheck['id'], 'Requested', sprintf('Item %s added to picklist and assigned to user', $i['barcode'])];
                    },
                    $itemsNotInPicklist
                )
            )->execute();

            // For items which ARE already in the picklist:
            // Update the picklist table in batch
            $this->modelClass::updateAll(
                ['user_id' => $tokenCheck['id']],
                ['item_id' => array_map(function($i) { return $i['id']; }, $itemsInPicklistNotMine)]
            );
            // Add to the item log in batch, using "Assigned" or "Reassigned" for the action
            Yii::$app->db->createCommand()->batchInsert(
                'item_log',
                ['item_id', 'user_id', 'action', 'details'],
                array_map(
                    function($i) use ($tokenCheck) {
                        return [$i['id'], $tokenCheck['id'], 'Assigned', sprintf('Item %s assigned on the picklist', $i['barcode'])];
                    },
                    $itemsUnassignedInPicklist
                )
            )->execute();
            Yii::$app->db->createCommand()->batchInsert(
                'item_log',
                ['item_id', 'user_id', 'action', 'details'],
                array_map(
                    function($i) use ($tokenCheck) {
                        return [$i['id'], $tokenCheck['id'], 'Reassigned', sprintf('Item %s reassigned on the picklist', $i['barcode'])];
                    },
                    $itemsAssignedInPicklistNotMine
                )
            )->execute();

            // Return updated picklist
            return $this->modelClass::find()->all();
        }
        else {
            throw new \yii\web\HttpException(403, "You do not have permission to assign items on the picklist.");
        }
    }

    // This function assigns all currently unassigned items to the current user.
    public function actionAssignAllToMe()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 40) {
            $unassignedPicklist = $this->modelClass::find()->where(['user_id' => null])->all();
            $picklistItemIdsUnassigned = array_map(
                function($p) { return $p['item_id']; },
                $unassignedPicklist
            );
            $items = \app\models\Item::find()->where(['id' => $picklistItemIdsUnassigned])->all();

            $itemsUnassignedInPicklist = array_filter(
                $items,
                function($i) use ($picklistItemIdsUnassigned) {
                    return in_array($i['id'], $picklistItemIdsUnassigned);
                }
            );

            // For items which ARE already in the picklist:
            // Update the picklist table in batch
            $this->modelClass::updateAll(
                ['user_id' => $tokenCheck['id']],
                ['item_id' => array_map(function($i) { return $i['id']; }, $itemsUnassignedInPicklist)]
            );
            // Add to the item log in batch, using "Assigned" or "Reassigned" for the action
            Yii::$app->db->createCommand()->batchInsert(
                'item_log',
                ['item_id', 'user_id', 'action', 'details'],
                array_map(
                    function($i) use ($tokenCheck) {
                        return [$i['id'], $tokenCheck['id'], 'Assigned', sprintf('Item %s assigned on the picklist', $i['barcode'])];
                    },
                    $itemsUnassignedInPicklist
                )
            )->execute();

            // Return updated picklist
            return $this->modelClass::find()->all();
        }
        else {
            throw new \yii\web\HttpException(403, "You do not have permission to assign items on the picklist.");
        }
    }

    // This function is used to unassign items in the picklist.
    public function actionUnassignItems()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 40) {
            $picklist = $this->modelClass::find()->all();
            $items = \app\models\Item::find()->where(['barcode' => $data['barcodes']])->all();

            $picklistItemIdsAlreadyAssigned = array_map(
                function($p) { return $p['item_id']; },
                array_filter(
                    $picklist,
                    function($p) use ($tokenCheck) {
                        return $p['user_id'] != null;
                    }
                )
            );

            $itemsAssigned = array_filter(
                $items,
                function($i) use ($picklistItemIdsAlreadyAssigned) {
                    return in_array($i['id'], $picklistItemIdsAlreadyAssigned);
                }
            );

            // For items which ARE already in the picklist:
            // Update the picklist table in batch
            $this->modelClass::updateAll(
                ['user_id' => null],
                ['item_id' => array_map(function($i) { return $i['id']; }, $itemsAssigned)]
            );
            // Add to the item log in batch, using "Assigned" or "Reassigned" for the action
            Yii::$app->db->createCommand()->batchInsert(
                'item_log',
                ['item_id', 'user_id', 'action', 'details'],
                array_map(
                    function($i) use ($tokenCheck) {
                        return [$i['id'], $tokenCheck['id'], 'Unassigned', sprintf('Item %s unassigned on the picklist', $i['barcode'])];
                    },
                    $itemsAssigned
                )
            )->execute();

            // Return updated picklist
            return $this->modelClass::find()->all();
        }
        else {
            throw new \yii\web\HttpException(403, "You do not have permission to unassign items on the picklist.");
        }
    }

    public function actionRemoveItems()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 50) {
            $itemsToRemove = \app\models\Item::find()
                    ->select("id, barcode")
                    ->where(['barcode' => $data['barcodes']])
                    ->all();
            $itemIdsToRemove = array_map(
                function($i) { return $i['id']; },
                $itemsToRemove
            );

            $this->modelClass::deleteAll(['item_id' => $itemIdsToRemove]);

            // Add to the item log in batch
            Yii::$app->db->createCommand()->batchInsert(
                'item_log',
                ['item_id', 'user_id', 'action', 'details'],
                array_map(
                    function($i) use ($tokenCheck) {
                        return [$i['id'], $tokenCheck['id'], 'Removed from picklist', sprintf("Item %s removed from picklist", $i['barcode'])];
                    },
                    $itemsToRemove
                )
            )->execute();

            return $this->modelClass::find()->all();
        }
        else {
            throw new \yii\web\HttpException(403, "You do not have permission to remove items from the picklist.");
        }
    }

    public function actionUnassignMine()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 40) {
            $myItems = \app\models\Item::find()
                    ->innerJoinWith('picklist', 'picklist.item_id = item.id')
                    ->where(['user_id' => $tokenCheck['id']])
                    ->all();
            $itemIdsToUpdate = array_map(
                function($i) { return $i['id']; },
                $myItems
            );

            $this->modelClass::updateAll(
                ['user_id' => null],
                ['item_id' => $itemIdsToUpdate]
            );

            // Add to the item log in batch
            Yii::$app->db->createCommand()->batchInsert(
                'item_log',
                ['item_id', 'user_id', 'action', 'details'],
                array_map(
                    function($i) use ($tokenCheck) {
                        return [$i['id'], $tokenCheck['id'], 'Unassigned', sprintf("Item %s unassigned from user's own picklist", $i['barcode'])];
                    },
                    $myItems
                )
            )->execute();

            return $this->modelClass::find()->all();
        }
        else {
            throw new \yii\web\HttpException(403, "You do not have permission to change picklist assignments.");
        }
    }
}
