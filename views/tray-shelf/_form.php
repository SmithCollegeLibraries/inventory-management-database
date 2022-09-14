<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model app\models\TrayShelf */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="tray-shelf-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'boxbarcode')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'shelf')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'row')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'side')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'ladder')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'shelf_number')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'shelf_depth')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'shelf_position')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'initials')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'added')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'timestamp')->textInput(['maxlength' => true]) ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Create' : 'Update', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
