<?php

declare(strict_types=1);

namespace TradeNPC\util;


use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;
use TradeNPC\TradeNPC;

class Utils {

    public static function makeRecipe(Item $buyA, Item $buyB, Item $sell): CompoundTag {
        return CompoundTag::create()
            ->setTag("buyA", $buyA->nbtSerialize())
            ->setTag("buyB", $buyB->nbtSerialize())
            ->setTag("sell", $sell->nbtSerialize())
            ->setInt("maxUses", 32767)
            ->setByte("rewardExp", 0)
            ->setInt("uses", 0)
            ->setString("label", "");
    }

    /**
     * @return TradeNPC[]
     */
    public static function getAllTradersInWorld(World $world) :array {
        $traders = [];
        foreach($world->getEntities() as $entity){
            if($entity instanceof TradeNPC){
                $traders[] = $entity;
            }
        }
        return $traders;
    }

}