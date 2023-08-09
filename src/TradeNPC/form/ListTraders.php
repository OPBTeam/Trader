<?php

declare(strict_types=1);

namespace TradeNPC\form;

use jojoe77777\FormAPI\SimpleForm;
use pocketmine\player\Player;
use TradeNPC\TradeNPC;

class ListTraders extends SimpleForm {

    private array $trades;

    /**
    * @param TradeNPC[] $trades
    */
    public function __construct(array $trades) {
        parent::__construct($this->getCallable());
        $this->trades = $trades;
        $this->setTitle("§l§aTradeNPC");
        foreach($trades as $trade){
            $this->addButton($trade->getNameTag());
        }
    }

    public function getCallable() :callable {
        return function(Player $player, $data) :void {
            if($data === null) return;
            $trader = $this->trades[$data];
            $player->sendForm(new EditTrader($trader));
        };
    }
}