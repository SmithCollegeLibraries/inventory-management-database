<?php

use app\models\Tray;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var app\models\TraySearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Trays';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="tray-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Create Tray', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            'barcode',
            'shelf_id',
            'depth',
            'position',
            //'active',
            [
                'class' => ActionColumn::class,
                'urlCreator' => function ($action, Tray $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 }
            ],
        ],
    ]); ?>


</div>
