<?php

declare(strict_types=1);

namespace TradeNPC\form;

use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\player\Player;
use TradeNPC\inventory\TradeEditInventory;
use TradeNPC\Main;
use TradeNPC\TradeNPC;

class EditTrader extends SimpleForm {

    private TradeNPC $trader;

    public function __construct(TradeNPC $trader)
    {
        parent::__construct($this->getCallable());
        $this->trader = $trader;
        $this->setTitle("§l§aTradeNPC Edit");
        $this->addButton("§l§aEdit Inventory");
        $this->addButton("§l§aRemove");
        $this->addButton("§l§aNPC Name");
    }

    public function getCallable() :callable {
        return function(Player $player, $data) :void {
            if($data === null) return;
            switch($data){
                case 0:
                    (new TradeEditInventory())->send($player, $this->trader);
                    break;
                case 1:
                    Main::getInstance()->removeTrader($this->trader);
                    $player->sendMessage("§aTrader§e {$this->trader->getNameTag()} §aRemoved!");
                    break;
                case 2:
                    $player->sendForm(new EditName($this->trader));
                    break;
            }
        };
    }
}

class EditName extends CustomForm {

    private TradeNPC $trader;

    public function __construct(TradeNPC $trader)
    {
        parent::__construct($this->getCallable());
        $this->trader = $trader;
        $this->setTitle("§l§aTradeNPC Edit Name");
        $this->addInput("§l§aName", $trader->getNameTag());
    }

    public function getCallable(): ?callable
    {
        return function(Player $player, ?array $data) {
            if($data === null) return;
            $name = $data[0] ?? "Trader";
            $this->trader->setNameTag($name);
            $player->sendMessage("§aTrader§e $name §aName Changed!");
        };
    }
}