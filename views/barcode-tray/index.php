<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel app\models\BarcodeTraySearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Barcode Trays';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="barcode-tray-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <p>
        <?= Html::a('Create Barcode Tray', ['create'], ['class' => 'btn btn-success']) ?>
    </p>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            'boxbarcode',
            'barcode',
            'stream',
            'initials',
            // 'added',
            // 'timestamp',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>
</div>
