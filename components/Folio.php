<?php

namespace app\components;

use yii\httpclient\Client;

class Folio
{
    public static function fullLookup($barcode)
    {
        // Don't look the item up in FOLIO if it doesn't consist completely
        // of numbers, letters, or a hyphen
        if (!preg_match('/^[a-zA-Z0-9-]+$/', $barcode)) {
            return null;
        }
        $client = new Client(['baseUrl' => "http://libtools2.smith.edu/folio/web/search/search-inventory"]);
        $response = $client->createRequest()
            ->setMethod('get')
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl([
                'type' => 'inventory',
                'query' => sprintf("barcode==%s", $barcode),
            ])
            ->send();
        if ($response->isOk) {
            return $response->data;
        }
        else {
            return null;
        }
    }

    public static function partialLookup($barcode)
    {
        // Don't look the item up in FOLIO if it doesn't consist completely
        // of numbers, letters, or a hyphen
        if (!preg_match('/^[a-zA-Z0-9-]+$/', $barcode)) {
            return null;
        }
        $client = new Client(['baseUrl' => "http://libtools2.smith.edu/folio/web/search/search-inventory"]);
        $response = $client->createRequest()
            ->setMethod('get')
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl([
                'query' => sprintf("(items.barcode==%s)", $barcode),
            ])
            ->send();
        if ($response->isOk) {
            $results = $response->data;
            try {
                // If the item is in FOLIO, there should be exactly one result
                // for this barcode.
                $instance = reset($results["data"]["instances"]);
                // The title is located on the instance record
                $title = $instance["title"];
                // To get the call number, we will have to look at the items,
                // find the item that matches on the barcode, and then get the
                // call number from effectiveCallNumberComponents.
                $items = array_filter($instance["items"], function($item) use ($barcode) {
                    return isset($item["barcode"]) && $item["barcode"] == $barcode;
                });
                $correctItem = reset($items);
                $callNumber = $correctItem["effectiveCallNumberComponents"]["callNumber"];
                $status = isset($correctItem["status"]) ? $correctItem["status"]["name"] : null;
                // Get annexLocation from settings: this will be in JSON.
                // It is the value associated with the annexLocation field in the Settings table in the db.
                $annexLocationsJson = \app\models\Setting::find()->where(['name' => 'annexLocation'])->one();
                $annexLocations = json_decode($annexLocationsJson->value, true);
                return [
                    "barcode" => $barcode,
                    "title" => $title,
                    "callNumber" => $callNumber,
                    "status" => $status,
                    "annex" => in_array($correctItem["effectiveLocationId"], $annexLocations),
                ];
            } catch (\Exception $e) {
                return [];
            }
        }
        else {
            return null;
        }
    }

    public static function getTitleAndVolume($barcode)
    {
        // Don't look the item up in FOLIO if it doesn't consist completely
        // of numbers, letters, or a hyphen
        if (!preg_match('/^[a-zA-Z0-9-]+$/', $barcode)) {
            return null;
        }
        $client = new Client(['baseUrl' => "http://libtools2.smith.edu/folio/web/search/search-inventory"]);
        $response = $client->createRequest()
            ->setMethod('get')
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl([
                'type' => 'inventory',
                'query' => sprintf("barcode==%s", $barcode),
            ])
            ->send();
        if ($response->isOk) {
            $results = $response->data;
            try {
                // If the item is in FOLIO, there should be exactly one result
                // for this barcode.
                $itemsList = $results["data"]["items"];
                $matchingItems = array_filter($itemsList, function($item) use ($barcode) {
                    return isset($item["barcode"]) && $item["barcode"] == $barcode;
                });
                $correctItem = reset($matchingItems);
                $title = isset($correctItem["title"]) ? $correctItem["title"] : null;
                $volume = isset($correctItem["volume"]) ? $correctItem["volume"] : "";
                $copy = isset($correctItem["copyNumber"]) && $correctItem["copyNumber"] != 1 ? "c." . $correctItem["copyNumber"] : "";
                if ($volume && $copy) {
                    $volumeAndCopy = $volume . " " . $copy;
                }
                else {
                    $volumeAndCopy = $volume . $copy;
                }
                return [
                    "barcode" => $barcode,
                    "title" => $title,
                    "volume" => $volumeAndCopy ? $volumeAndCopy : null,
                ];
            } catch (\Exception $e) {
                return [];
            }
        }
        else {
            return null;
        }
    }

    // TODO: If performance is slow, modify this to get titles and volumes
    // in this report, instead of separately
    public static function getPicklist($institution)
    {
        $servicePoints = json_decode(\app\models\Setting::findOne(['name' => 'servicePoints'])->value, true);
        $servicePoint = $servicePoints[$institution];
        $client = new Client(['baseUrl' => "https://libtools2.smith.edu/folio/web/search/search-circulation?id=" . $servicePoint]);
        $response = $client->createRequest()
            ->setMethod('get')
            ->setFormat(Client::FORMAT_JSON)
            ->send();
        if ($response->isOk) {
            $results = $response->data;
            return $results["data"];
        }
        else {
            return null;
        }
    }

    public static function handleMarkFolioAnomaly($item, $userId)
    {
        $folioInfo = \app\components\Folio::partialLookup($item["barcode"]);
        $notAvailable = false;
        $notAnnex = false;
        if (isset($folioInfo["status"]) && $folioInfo["status"] != "Available") {
            $notAvailable = true;
        }
        if (isset($folioInfo["annex"]) && !$folioInfo["annex"]) {
            $notAnnex = true;
        }

        if ($notAvailable || $notAnnex) {
            // If the item isn't already on Colin's backlog list, flag it and log
            if (\app\models\ColinBacklog::find()->where(['barcode' => $item->barcode])->count() == 0) {
                if ($item->flag != 1) {
                    $item->flag = 1;
                    $item->save();
                }

                if ($notAvailable && $notAnnex) {
                    $flagReason = "it is not in the Annex in FOLIO and also has a status other than Available in FOLIO";
                }
                else if ($notAvailable) {
                    $flagReason = "it has a status other than Available in FOLIO";
                }
                else if ($notAnnex) {
                    $flagReason = "it is not in the Annex in FOLIO";
                }

                $itemLog = new \app\models\ItemLog;
                $itemLog->item_id = $item->id;
                $itemLog->action = 'Flagged';
                $itemLog->details = sprintf("Flagged item because %s: %s", $flagReason, $item->barcode);
                $itemLog->user_id = $userId;
                $itemLog->save();
            }

            return true;
        }
        else {
            // Unflag the item
            if ($item->flag != 0) {
                $item->flag = 0;
                $item->save();
            }

            return false;
        }
    }

}