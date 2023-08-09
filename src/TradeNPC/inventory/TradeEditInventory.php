<?php

declare(strict_types=1);

namespace TradeNPC\inventory;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\player\Player;
use TradeNPC\TradeNPC;
use TradeNPC\util\Utils;

class TradeEditInventory{

    public static function send(Player $player, TradeNPC $npc) :void {
        $inventory = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
        $itemTrades = $npc->getTradeItems();
        foreach ($itemTrades as $index => $item) {
            $item = $item->getValue();
            $buyItemA = Item::nbtDeserialize($item["buyA"]);
            $buyItemB = Item::nbtDeserialize($item["buyB"]);
            $sellItem = Item::nbtDeserialize($item["sell"]);
            $buySlotA = $index;
            $buySlotB = $index + 9;
            $sellSlot = $buySlotB + 9;
            if ($buySlotB >= 16) {
                break;
            }
            $inventory->getInventory()->setItem($buySlotA, $buyItemA);
            $inventory->getInventory()->setItem($buySlotB, $buyItemB);
            $inventory->getInventory()->setItem($sellSlot, $sellItem);
        }
        $inventory->setName("§l§0Trade Edit: " . $npc->getNameTag());
        $inventory->setInventoryCloseListener(function(Player $player, Inventory $inventory) use ($npc) {
            $recipes = [];
            for ($i = 0; $i < 8; $i ++) {
                $buyItemA = $inventory->getItem(($i));
                $buyItemB = $inventory->getItem(($buySlot = $i + 9));
                $sellItem = $inventory->getItem($buySlot + 9);
                if ($buyItemA->isNull() or $sellItem->isNull()) {
                    continue;
                }
                $recipes[] = Utils::makeRecipe($buyItemA, $buyItemB, $sellItem);
            }
            $npc->setRecipes($recipes);
        });
        $inventory->send($player);
    }

}