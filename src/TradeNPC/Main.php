<?php
declare(strict_types=1);

namespace TradeNPC;

use InvalidArgumentException;
use pocketmine\inventory\Inventory;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\player\Player;
use pocketmine\entity\{EntityFactory, EntityDataHelper};
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\sound\AnvilFallSound;
use pocketmine\world\sound\XpLevelUpSound;
use pocketmine\world\World;
use pocketmine\entity\Location;
use pocketmine\entity\Human;
use pocketmine\item\Item;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\event\inventory\InventoryOpenEvent;
use muqsit\invmenu\{InvMenu, InvMenuHandler, type\InvMenuTypeIds};
use pocketmine\command\{CommandSender, Command};
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\nbt\tag\{ByteArrayTag, CompoundTag, ListTag, StringTag};
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\network\mcpe\protocol\{ActorEventPacket, ContainerClosePacket, InventoryTransactionPacket, LoginPacket, types\ActorEvent};
use pocketmine\network\mcpe\protocol\types\inventory\{ItemStackWrapper,
    NetworkInventoryAction,
    UseItemOnEntityTransactionData,
    NormalTransactionData
};
use pocketmine\network\mcpe\JwtUtils;
use TradeNPC\form\CreateTrader;
use TradeNPC\form\ListTraders;
use TradeNPC\util\Utils;

class Main extends PluginBase implements Listener
{
    use SingletonTrait;

    public Inventory|null $currentWindow = null;

	protected array $deviceOSData = [];

	public bool $start = false;

    private InvMenu $menu;

	public function onLoad() :void
	{
		self::$instance = $this;
	}

	public function onEnable() :void
	{
        EntityFactory::getInstance()->register(TradeNPC::class, function (World $world, CompoundTag $nbt): TradeNPC {
            return new TradeNPC(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt);
        }, ['TradeNPC', 'Trade']);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if(!InvMenuHandler::isRegistered()){
            InvMenuHandler::register($this);
        }
		$this->menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
	}

	public function loadData(TradeNPC $npc) :void {
		if (file_exists($this->getDataFolder() . $npc->getNameTag() . ".dat")) {
			$nbt = (new LittleEndianNbtSerializer)->read(file_get_contents($this->getDataFolder() . $npc->getNameTag() . ".dat"));
		} else {
			$nbt = CompoundTag::create()
			->setTag("Recipes", new ListTag([]));
		}
		$npc->loadData($nbt);
	}
	
	public function saveAll() :void{
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$player->save();
		}

		foreach($this->getServer()->getWorldManager()->getWorlds() as $world){
			$world->save(true);
		}
	}
	
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
	{
		if (!$sender instanceof Player) {
			return true;
		}
        if(!isset($args[0])){
        	$sender->sendMessage("§cUse: /trader <create|edit>");
        	return true;
        }
		if($this->getServer()->isOp($sender->getName())){
            switch ($args[0]) {
                case "create":
                    $sender->sendForm(new CreateTrader());
                    break;
                case "edit":
                    $sender->sendForm(new ListTraders(Utils::getAllTradersInWorld($sender->getWorld())));
                    break;
                default:
                    foreach ([
                                 ["/trader create", "Create trader"],
                                 ["/trader edit", "Edit trader"],
                             ] as $usage) {
                    $sender->sendMessage($usage[0] . " - " . $usage[1]);
                    }
            }
        }
		return true;
	}

	/**
	 * @param DataPacketReceiveEvent $event
	 *
	 * @author
	 */
	public function handleDataPacket(DataPacketReceiveEvent $event) :void
	{
		$player = $event->getOrigin()->getPlayer();
		$packet = $event->getPacket();
		if ($packet instanceof ActorEventPacket) {
			if ($packet->eventId === ActorEvent::COMPLETE_TRADE) {
				if (!isset(TradeDataPool::$interactNPCData[$player->getName()])) {
					return;
				}
				$data = TradeDataPool::$interactNPCData[$player->getName()]->getShopCompoundTag()->getListTag("Recipes")->get($packet->eventData);
				if ($data instanceof CompoundTag) {
					$buyA = Item::nbtDeserialize($data->getCompoundTag("buyA"));
					$buyB = Item::nbtDeserialize($data->getCompoundTag("buyB"));
					$sell = Item::nbtDeserialize($data->getCompoundTag("sell"));
					if ($player->getInventory()->contains($buyA) and $player->getInventory()->contains($buyB)) {// Prevents https://github.com/alvin0319/TradeNPC/issues/3
						$player->getInventory()->removeItem($buyA);
						$player->getInventory()->removeItem($buyB);
						$player->getInventory()->addItem($sell);
						$sound = new XpLevelUpSound(10);
					} else {
						$sound = new AnvilFallSound();
					}
                    $player->getWorld()->addSound($player->getPosition(), $sound);
				}
			}
		}
		if ($packet instanceof InventoryTransactionPacket) {
			//7: PC
			if($packet->trData instanceof NormalTransactionData){
				foreach ($packet->trData->getActions() as $action) {
					if ($action instanceof NetworkInventoryAction) {
                        if($action->sourceType != NetworkInventoryAction::SOURCE_CONTAINER || $action->sourceType != NetworkInventoryAction::SOURCE_TODO){
                            continue;
                        }
						if (isset(TradeDataPool::$windowIdData[$player->getName()]) and $action->windowId === TradeDataPool::$windowIdData[$player->getName()]) {
                            $convertItemStack = function ($item) {
                                if ($item instanceof ItemStackWrapper) {
                                    return TypeConverter::getInstance()->netItemStackToCore($item->getItemStack());
                                }
                                return $item;
                            };
                            $oldItem = $convertItemStack($action->oldItem);
                            $newItem = $convertItemStack($action->newItem);
							$player->getInventory()->addItem($oldItem);
							$player->getInventory()->removeItem($newItem);
						}
					}
				}
			} elseif($packet->trData instanceof UseItemOnEntityTransactionData) {
				$entity = $player->getWorld()->getEntity($packet->trData->getActorRuntimeId());
				if ($entity instanceof TradeNPC) {
					$this->setCWindow($entity->getTradeInventory(), $player);
				}
			}
		}
		if ($packet instanceof LoginPacket) {
			$data = JwtUtils::parse($packet->clientDataJwt);
            $device = (int)$data[1]["DeviceOS"];
			$this->deviceOSData[strtolower($data[1]["ThirdPartyName"])] = $device;
		}
		if ($packet instanceof ContainerClosePacket) {
			if (isset(TradeDataPool::$windowIdData[$player->getName()])) {
				$pk = new ContainerClosePacket();
				$pk->windowId = 255; // ??
				$player->getNetworkSession()->sendDataPacket($pk);
			}
		}
	}
	
	public function setCWindow(TradeInventory $inventory, $player) : bool{
		if($inventory === $this->currentWindow){
			return true;
		}
		$ev = new InventoryOpenEvent($inventory, $player);
		$ev->call();
		if($ev->isCancelled()){
			return false;
		}

		//TODO: client side race condition here makes the opening work incorrectly
		$player->removeCurrentWindow();

		if($player->getNetworkSession()->getInvManager() === null){
			throw new InvalidArgumentException("Player cannot open inventories in this state");
		}
		$inventory->onOpen($player);
		$this->currentWindow = $inventory;
		return true;
	}

    public function makeTrader(string $name, Player $player) :TradeNPC {
        $nbt = CompoundTag::create();
        $nbt->setTag("Name", new StringTag($player->getSkin()->getSkinId()));
        $nbt->setTag("Data", new ByteArrayTag($player->getSkin()->getSkinData()));
        $nbt->setTag("CapeData", new ByteArrayTag($player->getSkin()->getCapeData()));
        $nbt->setTag("GeometryName", new StringTag($player->getSkin()->getGeometryName()));
        $nbt->setTag("GeometryData", new ByteArrayTag($player->getSkin()->getGeometryData()));
        $entity = new TradeNPC(Location::fromObject($player->getPosition()->add(0.5, 0, 0.5), $player->getPosition()->getWorld(), $player->getLocation()->getYaw() ?? 0, $player->getLocation()->getPitch() ?? 0), $player->getSkin(), $nbt);
        $entity->setNameTag($name);
        $entity->setNameTagAlwaysVisible();
        $entity->spawnToAll();
        return $entity;
    }

    public function removeTrader(TradeNPC $entity) :void {
        $name = $entity->getNameTag();
        $entity->flagForDespawn();
        if (!file_exists($this->getDataFolder() . $name . ".dat")) {
            $this->getLogger()->error("Cannot find the file of the trader " . $name);
            return;
        }
        unlink($this->getDataFolder() . $name . ".dat");
        $this->saveAll();
    }

	public function onQuit(PlayerQuitEvent $event)
	{
		$player = $event->getPlayer();
		if (isset($this->deviceOSData[strtolower($player->getName())])) unset($this->deviceOSData[strtolower($player->getName())]);
	}

	public function saveData(TradeNPC $npc)
	{
		if(!file_exists($this->getDataFolder() . $npc->getNameTag() . ".dat")) {
			fopen($this->getDataFolder() . $npc->getNameTag() . ".dat", "w");
		}
		file_put_contents($this->getDataFolder() . $npc->getNameTag() . ".dat", $npc->getSaveNBT());
	}

	public function onDisable() :void
	{
		foreach ($this->getServer()->getWorldManager()->getWorlds() as $level) {
			foreach ($level->getEntities() as $entity) {
				if ($entity instanceof TradeNPC) {
					file_put_contents($this->getDataFolder() . $entity->getNameTag() . ".dat", $entity->getSaveNBT());
				}
			}
		}
	}
}
