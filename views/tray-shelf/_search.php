<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model app\models\TrayShelfSearch */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="tray-shelf-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'boxbarcode') ?>

    <?= $form->field($model, 'shelf') ?>

    <?= $form->field($model, 'row') ?>

    <?= $form->field($model, 'side') ?>

    <?php // echo $form->field($model, 'ladder') ?>

    <?php // echo $form->field($model, 'shelf_number') ?>

    <?php // echo $form->field($model, 'shelf_depth') ?>

    <?php // echo $form->field($model, 'shelf_position') ?>

    <?php // echo $form->field($model, 'initials') ?>

    <?php // echo $form->field($model, 'added') ?>

    <?php // echo $form->field($model, 'timestamp') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
