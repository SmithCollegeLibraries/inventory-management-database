<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\TrayShelf;

/**
 * TrayShelfSearch represents the model behind the search form about `app\models\TrayShelf`.
 */
class TrayShelfSearch extends TrayShelf
{
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id'], 'integer'],
            [['boxbarcode', 'shelf', 'row', 'side', 'ladder', 'shelf_number', 'shelf_depth', 'shelf_position', 'initials', 'added', 'timestamp'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
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
        $query = TrayShelf::find();

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
        ]);

        $query->andFilterWhere(['like', 'boxbarcode', $this->boxbarcode])
            ->andFilterWhere(['like', 'shelf', $this->shelf])
            ->andFilterWhere(['like', 'row', $this->row])
            ->andFilterWhere(['like', 'side', $this->side])
            ->andFilterWhere(['like', 'ladder', $this->ladder])
            ->andFilterWhere(['like', 'shelf_number', $this->shelf_number])
            ->andFilterWhere(['like', 'shelf_depth', $this->shelf_depth])
            ->andFilterWhere(['like', 'shelf_position', $this->shelf_position])
            ->andFilterWhere(['like', 'initials', $this->initials])
            ->andFilterWhere(['like', 'added', $this->added])
            ->andFilterWhere(['like', 'timestamp', $this->timestamp]);

        return $dataProvider;
    }
}
