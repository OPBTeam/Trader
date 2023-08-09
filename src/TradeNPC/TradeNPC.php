<?php
declare(strict_types=1);

namespace TradeNPC;

use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\nbt\tag\CompoundTag;

class TradeNPC extends Human
{
    
	/** @var CompoundTag|null */
	protected ?CompoundTag $shop = null;

	public function getName() : string{
		return "TradeNPC";
	}

    public function setRecipes(array $recipes): void
    {
        $tagFunction = $this->getTagFunction($this->shop);
        if($tagFunction->getListTag("Recipes") !== null) {
            $tagFunction->removeTag("Recipes");
        }
        $tagFunction->setTag("Recipes", new ListTag());
        foreach ($recipes as $recipe) {
            $this->addRecipe($recipe);
        }
    }

    public function addRecipe(CompoundTag $tag) :void
    {
        $this->getTagFunction($this->shop)->getListTag("Recipes")->push($tag);
    }

    public function getTagFunction($tag)
    {
        return $tag instanceof CompoundTag ? $tag : $tag->getTag();
    }
    
	public function getShopCompoundTag(): ?CompoundTag
    {
		if($this->shop instanceof CompoundTag){
			return $this->shop;
		}
        return null;
	}

	public function saveNBT(): CompoundTag
	{
		$nbt = parent::saveNBT();
		Main::getInstance()->saveData($this);
		return $nbt;
	}

	public function getSaveNBT(): string
	{
		return (new LittleEndianNbtSerializer)->write($this->getTreeRoot($this->shop));
	}

	public function loadData($nbt): void
	{
		$this->shop = $nbt;
	}
	public function getTreeRoot($tag): ?TreeRoot
    {
		if($tag instanceof CompoundTag) {
			return new TreeRoot($tag);
		} elseif($tag instanceof TreeRoot) {
			return $tag;
		}
        return null;
	}

	public function initEntity(CompoundTag $nbt): void
	{
		parent::initEntity($nbt);
		if ($this->shop === null) {
			Main::getInstance()->loadData($this);
		}
		$this->setNameTagAlwaysVisible();
	}

	public function getTradeInventory(): TradeInventory
	{
		return new TradeInventory($this);
	}

	public function attack(EntityDamageEvent $source): void
	{
        $source->cancel();
	}

    public function getTradeItems() :array
    {
        $recipes = $this->getShopCompoundTag()->getListTag("Recipes");
        $items = [];
        if($recipes !== null) {
            foreach ($recipes as $recipe) {
                $items[] = $recipe;
            }
        }
        return $items;
    }
}