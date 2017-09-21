<?php

namespace app\controllers;
use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use app\models\Aleph;

class InternalRequestsController extends ActiveController
{
	public $modelClass = 'app\models\InternalRequests';

}
