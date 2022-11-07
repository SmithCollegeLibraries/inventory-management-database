<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Shelf $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="shelf-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'barcode')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'row')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'side')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'ladder')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'rung')->textInput(['maxlength' => true]) ?>

    <div class="form-group">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
