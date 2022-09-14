<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model app\models\BarcodeTray */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="barcode-tray-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'boxbarcode')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'barcode')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'stream')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'initials')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'added')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'timestamp')->textInput(['maxlength' => true]) ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Create' : 'Update', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
