<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model app\models\RecordInformationSearch */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="record-information-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'barcode') ?>

    <?= $form->field($model, 'title') ?>

    <?= $form->field($model, 'callnumber') ?>

    <?= $form->field($model, 'call_number_normalized') ?>

    <?php // echo $form->field($model, 'isbn') ?>

    <?php // echo $form->field($model, 'issn') ?>

    <?php // echo $form->field($model, 'img') ?>

    <?php // echo $form->field($model, 'status') ?>

    <?php // echo $form->field($model, 'check_out_time') ?>

    <?php // echo $form->field($model, 'return_time') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
