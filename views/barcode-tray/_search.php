<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model app\models\BarcodeTraySearch */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="barcode-tray-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'boxbarcode') ?>

    <?= $form->field($model, 'barcode') ?>

    <?= $form->field($model, 'stream') ?>

    <?= $form->field($model, 'initials') ?>

    <?php // echo $form->field($model, 'added') ?>

    <?php // echo $form->field($model, 'timestamp') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
