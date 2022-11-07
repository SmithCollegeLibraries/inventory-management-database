<?php

namespace app\models;

class User extends \yii\db\ActiveRecord implements \yii\web\IdentityInterface
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['email'], 'required'],
            [['name'], 'required'],
            [['level'], 'required'],
            [['passwordhash'], 'required'],
            [['name'], 'string', 'max' => 31],
            [['level'], 'integer', 'max' => 100],
            [['email'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'email' => 'Email address',
            'name' => 'Name',
            'level' => 'Level (0 = Viewer, 40 = Staff, 100 = Admin)',
            'passwordhash' => 'Password hash',
            'access_token' => 'Access token',
            'active' => 'Active',
        ];
    }

    /**
     * Gets query for [[CollectionLogs]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCollectionLogs()
    {
        return $this->hasMany(CollectionLog::class, ['user_id' => 'id']);
    }

    /**
     * Gets query for [[ItemLogs]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getItemLogs()
    {
        return $this->hasMany(ItemLog::class, ['user_id' => 'id']);
    }

    /**
     * Gets query for [[TrayLogs]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTrayLogs()
    {
        return $this->hasMany(TrayLog::class, ['user_id' => 'id']);
    }

    /**
     * Gets query for [[ShelfLogs]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getShelfLogs()
    {
        return $this->hasMany(ShelfLog::class, ['user_id' => 'id']);
    }

    /**
     * Gets query for [[SettingLogs]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSettingLogs()
    {
        return $this->hasMany(SettingLog::class, ['user_id' => 'id']);
    }


    /**
     * @inheritdoc
     */
    public static function findIdentity($id)
    {
        return isset(self::$users[$id]) ? new static(self::$users[$id]) : null;
    }

    /**
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        return static::findOne(['access_token' => $token]);
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        return $this->authKey;
    }

    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        return $this->authKey === $authKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        return Yii::$app->getSecurity()->validatePassword($password, $this->passwordhash);
    }
}
