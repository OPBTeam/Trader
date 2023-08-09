<?php

declare(strict_types=1);

namespace TradeNPC\form;

use jojoe77777\FormAPI\CustomForm;
use pocketmine\player\Player;
use TradeNPC\inventory\TradeEditInventory;
use TradeNPC\Main;

class CreateTrader extends CustomForm {

    public function __construct()
    {
        parent::__construct($this->getCallable());
        $this->setTitle("§l§aTradeNPC Create");
        $this->addInput("§l§aName");
        $this->addToggle("§l§aHand Item?");
    }

    public function getCallable(): ?callable
    {
        return function(Player $player, ?array $data) {
            if($data === null) return;
            $name = $data[0] ?? "Trader";
            $hand = $data[1];
            $trader = Main::getInstance()->makeTrader($name, $player);
            if($hand){
                $item = $player->getInventory()->getItemInHand();
                $trader->getInventory()->setItemInHand($item);
            }

            $player->sendMessage("§aTrader§e $name §aCreated!");
            (new TradeEditInventory())->send($player, $trader);
        };
    }
}