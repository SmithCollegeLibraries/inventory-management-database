<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;

class RecordApiController extends ActiveController
{
	public $modelClass = 'app\models\RecordInformation';
}
