<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model app\models\RecordInformation */

$this->title = $model->title;
$this->params['breadcrumbs'][] = ['label' => 'Record Informations', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="record-information-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Update', ['update', 'id' => $model->barcode], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Delete', ['delete', 'id' => $model->barcode], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Are you sure you want to delete this item?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'barcode',
            'title',
            'callnumber',
            'call_number_normalized',
            'isbn',
            'issn',
            'img:ntext',
            'status',
            'check_out_time',
            'return_time',
        ],
    ]) ?>

</div>
