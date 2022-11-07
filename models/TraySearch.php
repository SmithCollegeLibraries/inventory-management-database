<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\Tray;

/**
 * TraySearch represents the model behind the search form of `app\models\Tray`.
 */
class TraySearch extends Tray
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'shelf_id', 'active'], 'integer'],
            [['barcode', 'depth', 'position'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = Tray::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'shelf_id' => $this->shelf_id,
            'active' => $this->active,
        ]);

        $query->andFilterWhere(['like', 'barcode', $this->barcode])
            ->andFilterWhere(['like', 'depth', $this->depth])
            ->andFilterWhere(['like', 'position', $this-position]);

        return $dataProvider;
    }
}
