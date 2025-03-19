<?php

namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Expression;

use app\models\Setting;
use app\models\SettingLog;
use app\models\ItemLog;
use app\models\TrayLog;
use app\models\CacheFillRate;

const ANNEX_START_DATE = '2017-05-01';


class CacheTableController extends Controller
{
    /* This is called as a cron job daily to avoid long waits when
     * viewing fill rate reports */
    public static function actionFillRates(?int $user_id=null)
    {
        // Cache the item fill rate values for the given year and month, and
        // all the months following
        function cacheItemFillRate($year, $month)
        {
            $collectionSizeCounts = ItemLog::find()
                ->select([
                    new Expression('YEAR(item_log.timestamp) AS year'),
                    new Expression('MONTH(item_log.timestamp) AS month'),
                    'collection.id AS collection_id',
                    'size.id AS size_id',
                    'COUNT(*) AS count'
                ])
                ->leftJoin('item', 'item_log.item_id = item.id')
                ->leftJoin('tray', 'item.tray_id = tray.id')
                ->leftJoin('collection', 'item.collection_id = collection.id')
                ->leftJoin('size', 'tray.size_id = size.id')
                ->where(['item.active' => 1])
                ->andWhere(['item_log.action' => "Added"])
                // Where the timestamp is on or after the given year and month
                ->andWhere(['>=', 'DATE_FORMAT(item_log.timestamp, "%Y-%m")', "$year-$month"])
                ->groupBy(['YEAR(timestamp)', 'MONTH(timestamp)', 'collection.id', 'size.id'])
                ->asArray()
                ->all();

            // For each row in the results, add a row to the CacheFillRate table
            // with the year, month, collection, size, and item count --
            // or update the count if the row already exists.
            foreach ($collectionSizeCounts as $row) {
                $cacheFillRate = CacheFillRate::find()
                    ->where([
                        'cache_fill_rate.year' => $row['year'],
                        'cache_fill_rate.month' => $row['month'],
                        'collection_id' => $row['collection_id'],
                        'size_id' => $row['size_id'],
                    ])
                    ->one();
                if ($cacheFillRate) {
                    $cacheFillRate->item_count = $row['count'];
                    $cacheFillRate->save();
                }
                else {
                    $cacheFillRate = new CacheFillRate();
                    $cacheFillRate->year = $row['year'];
                    $cacheFillRate->month = $row['month'];
                    $cacheFillRate->collection_id = $row['collection_id'];
                    $cacheFillRate->size_id = $row['size_id'];
                    $cacheFillRate->item_count = $row['count'] ? $row['count'] : null;
                    $cacheFillRate->save();
                }
            }
        }

        function cacheTrayFillRate($year, $month)
        {
            $collectionSizeCounts = TrayLog::find()
                ->select([
                    new Expression('YEAR(tray_log.timestamp) AS year'),
                    new Expression('MONTH(tray_log.timestamp) AS month'),
                    'collection.id AS collection_id',
                    'size.id AS size_id',
                    'COUNT(*) AS count'
                ])
                ->leftJoin('tray', 'tray_log.tray_id = tray.id')
                ->leftJoin('collection', 'tray.collection_id = collection.id')
                ->leftJoin('size', 'tray.size_id = size.id')
                ->where(['tray.active' => 1])
                ->andWhere(['tray_log.action' => "Added"])
                // Where the timestamp is on or after the given year and month
                ->andWhere(['>=', 'DATE_FORMAT(tray_log.timestamp, "%Y-%m")', "$year-$month"])
                ->groupBy(['YEAR(timestamp)', 'MONTH(timestamp)', 'collection.id', 'size.id'])
                ->asArray()
                ->all();

            // For each row in the results, add a row to the CacheFillRate table
            // with the year, month, collection, size, and tray count --
            // or update the count if the row already exists.
            foreach ($collectionSizeCounts as $row) {
                $cacheFillRate = CacheFillRate::find()
                    ->leftJoin('collection', 'cache_fill_rate.collection_id = collection.id')
                    ->leftJoin('size', 'cache_fill_rate.size_id = size.id')
                    ->where([
                        'cache_fill_rate.year' => $row['year'],
                        'cache_fill_rate.month' => $row['month'],
                        'collection_id' => $row['collection_id'],
                        'size_id' => $row['size_id'],
                    ])
                    ->one();
                if ($cacheFillRate) {
                    $cacheFillRate->tray_count = $row['count'];
                    $cacheFillRate->save();
                }
                else {
                    $cacheFillRate = new CacheFillRate();
                    $cacheFillRate->year = $row['year'];
                    $cacheFillRate->month = $row['month'];
                    $cacheFillRate->collection_id = $row['collection_id'];
                    $cacheFillRate->size_id = $row['size_id'];
                    $cacheFillRate->tray_count = $row['count'];
                    $cacheFillRate->save();
                }
            }
        }

        function cacheShelfFillRate($year, $month)
        {
            $collectionSizeCounts = TrayLog::find()
                ->select([
                    new Expression('YEAR(tray_log.timestamp) AS year'),
                    new Expression('MONTH(tray_log.timestamp) AS month'),
                    'collection.id AS collection_id',
                    'size.id AS size_id',
                    new Expression('COUNT(DISTINCT shelf.id) AS count'),
                ])
                ->leftJoin('tray', 'tray_log.tray_id = tray.id')
                ->leftJoin('shelf', 'tray.shelf_id = shelf.id')
                ->leftJoin('collection', 'tray.collection_id = collection.id')
                ->leftJoin('size', 'tray.size_id = size.id')
                ->where([
                    'tray_log.action' => 'Added',
                    'tray.active' => 1,
                ])
                ->andWhere(['tray.size_id' => new \yii\db\Expression('shelf.size_id')])
                ->andWhere(['tray.collection_id' => new \yii\db\Expression('shelf.collection_id')])
                ->andWhere([
                    'tray_log.timestamp' => new \yii\db\Expression(
                        '(SELECT MIN(tl2.timestamp)
                        FROM tray_log tl2
                        JOIN tray t2 ON tl2.tray_id = t2.id
                        WHERE t2.shelf_id = tray.shelf_id
                        AND tl2.action = "Added")'
                    )
                ])
                // Where the timestamp is on or after the given year and month
                ->andWhere(['>=', 'DATE_FORMAT(tray_log.timestamp, "%Y-%m")', "$year-$month"])
                ->groupBy(['year', 'month', 'collection.id', 'size.id'])
                ->asArray()
                ->all();

            // For each row in the results, add a row to the CacheFillRate table
            // with the year, month, collection, size, and shelf count --
            // or update the count if the row already exists.
            foreach ($collectionSizeCounts as $row) {
                $cacheFillRate = CacheFillRate::find()
                    ->leftJoin('collection', 'cache_fill_rate.collection_id = collection.id')
                    ->leftJoin('size', 'cache_fill_rate.size_id = size.id')
                    ->where([
                        'cache_fill_rate.year' => $row['year'],
                        'cache_fill_rate.month' => $row['month'],
                        'collection_id' => $row['collection_id'],
                        'size_id' => $row['size_id'],
                    ])
                    ->one();
                if ($cacheFillRate) {
                    $cacheFillRate->shelf_count = $row['count'];
                    $cacheFillRate->save();
                } else {
                    $cacheFillRate = new CacheFillRate();
                    $cacheFillRate->year = $row['year'];
                    $cacheFillRate->month = $row['month'];
                    $cacheFillRate->collection_id = $row['collection_id'];
                    $cacheFillRate->size_id = $row['size_id'];
                    $cacheFillRate->shelf_count = $row['count'];
                    $cacheFillRate->save();
                }
            }
        }

        // Look for the value of the setting fillRateLastRun
        $lastRun = Setting::find()->where(['name' => 'fillRateLastRun'])->one();
        if ($lastRun && $lastRun->value) {
            $lastRun = date('Y-m-d', strtotime($lastRun->value));
        }
        else {
            $lastRun = date('Y-m-d', strtotime(ANNEX_START_DATE));
        }

        $lastRunYear = (int)date('Y', strtotime($lastRun));
        $lastRunMonth = (int)date('m', strtotime($lastRun));

        $today = date('Y-m-d');

        if ($today === $lastRun) {
            return;
        }

        // Cache the fill rates for the last run month
        cacheItemFillRate($lastRunYear, $lastRunMonth);
        cacheTrayFillRate($lastRunYear, $lastRunMonth);
        cacheShelfFillRate($lastRunYear, $lastRunMonth);

        // Update the setting fillRateLastRun to today
        $lastRunSetting = Setting::find()->where(['name' => 'fillRateLastRun'])->one();
        if ($lastRunSetting) {
            $lastRunSetting->value = $today;
            $lastRunSetting->save();
        }
        else {
            $lastRunSetting = new Setting();
            $lastRunSetting->name = 'fillRateLastRun';
            $lastRunSetting->value = $today;
            $lastRunSetting->save();
        }
        // Log the setting update in SettingLog
        $settingLog = new SettingLog();
        $settingLog->setting_id = $lastRunSetting->id;
        $settingLog->value = $today;
        $settingLog->user_id = $user_id;
        $settingLog->save();

        return ExitCode::OK;
    }
}
