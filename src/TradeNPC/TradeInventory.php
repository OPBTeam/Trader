<?php
declare(strict_types=1);

namespace TradeNPC;

use pocketmine\block\inventory\BlockInventory;
use pocketmine\inventory\BaseInventory;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\network\mcpe\protocol\UpdateTradePacket;
use pocketmine\player\Player;
use pocketmine\item\Item;
use pocketmine\inventory\SimpleInventory;
use pocketmine\world\Position;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\nbt\tag\CompoundTag;

class TradeInventory extends SimpleInventory implements BlockInventory
{

	protected TradeNPC $npc;
    private Position $holder;
	
	public function internalSetContents(array $items) :void {}
	
	public function internalSetItem(int $index, Item $item) :void {}
	
	public function getSize() :int {
		return 3;
	}

	public function __construct(TradeNPC $holder)
	{
		parent::__construct(3);
		$this->npc = $holder;
	    $this->holder = new Position(0, 0, 0, null);
	}
	
	public function getHolder() :Position {
		return $this->holder;
    }

	public function getName(): string
	{
		return "TradeInventory";
	}

	public function getDefaultSize(): int
	{
		return 3; // TODO: Enable the slot 2
	}

	public function getNetworkType(): int
	{
		return WindowTypes:: TRADING;
	}

	public function onOpen(Player $who): void
	{
		BaseInventory::onOpen($who);

		$pk = new UpdateTradePacket();
		$pk->displayName = $this->npc->getNameTag();
		$pk->windowId = $id = 3;
		$pk->isV2Trading = true;
		$pk->tradeTier = 3;
		$pk->playerActorUniqueId = $who->getId();
		$pk->traderActorUniqueId = $this->npc->getId();
		$pk->offers = $this->getOff($this->npc->getShopCompoundTag());
		$pk->isEconomyTrading = false;
		$who->getNetworkSession()->sendDataPacket($pk);
		TradeDataPool::$windowIdData[$who->getName()] = $id;
		TradeDataPool::$interactNPCData[$who->getName()] = $this->npc;
	}
	
	public function getOff($poss): ?CacheableNbt{
		if($poss instanceof TreeRoot){
			return new CacheableNbt($this->getTagFunction($poss->mustGetCompoundTag()));
		} elseif($poss instanceof CompoundTag){
			return new CacheableNbt($this->getTagFunction($this->npc->getShopCompoundTag()));
		}
        return null;
	}
    
    public function getTagFunction($tag) {
        return $tag instanceof CompoundTag ? $tag : $tag->getTag();
    }
    
	public function onClose(Player $who): void
	{
		BaseInventory::onClose($who);
		unset(TradeDataPool::$windowIdData[$who->getName()]);
		unset(TradeDataPool::$interactNPCData[$who->getName()]);
	}
}
