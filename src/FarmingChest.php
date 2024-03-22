<?php

namespace farmingchest;

use pocketmine\block\{Crops, Stem, VanillaBlocks};
use pocketmine\block\tile\Chest;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use ReflectionException;

final class FarmingChest extends PluginBase {

    use SingletonTrait;

    /**
     * @return void
     */
    protected function onLoad(): void {
        self::setInstance($this);
        $this->saveDefaultConfig();
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    protected function onEnable(): void {
        $config = $this->getConfig();

        $this->getServer()->getPluginManager()->registerEvent(PlayerInteractEvent::class, function (PlayerInteractEvent $event) use ($config): void {
            $block = $event->getBlock();
            if (
                $event->getAction() === $event::RIGHT_CLICK_BLOCK &&
                $block->hasSameTypeId(VanillaBlocks::TRAPPED_CHEST())
            ) {
                $position = $block->getPosition();
                $world = $position->getWorld();
                $tile = $world->getTile($position);
                if ($tile instanceof Chest) {
                    $result = [];
                    $minMaxOffset = 8;
                    [$initialX, $y, $initialZ] = [$position->x, $position->y, $position->z];
                    for ($x = $initialX - $minMaxOffset; $x <= $initialX + $minMaxOffset; $x++) {
                        for ($z = $initialZ - $minMaxOffset; $z <= $initialZ + $minMaxOffset; $z++) {
                            $blockToCheck = $world->getBlockAt($x, $y, $z);
                            if ($blockToCheck instanceof Crops && !$blockToCheck instanceof Stem) {
                                if ($blockToCheck->getAge() >= $blockToCheck::MAX_AGE) {
                                    foreach ($blockToCheck->getDropsForCompatibleTool($blockToCheck->asItem()) as $item) {
                                        $itemName = $item->getVanillaName();
                                        $result[$itemName] = ($result[$itemName] ?? 0) + $item->getCount();
                                        if ($tile->getInventory()->canAddItem($item)) {
                                            $tile->getInventory()->addItem($item);
                                        } else {
                                            $world->dropItem($position->asVector3(), $item, new Vector3(lcg_value() * 0.25 - 0.125, 0.25, lcg_value() * 0.25 - 0.125));
                                        }
                                    }
                                    $world->setBlock($blockToCheck->getPosition(), $blockToCheck->setAge(0));
                                }
                            }
                        }
                    }
                    $player = $event->getPlayer();
                    if (array_sum($result) > 0 && !empty($result)) {
                        $player->sendMessage($config->getNested("farming-chest.message.farming-chest-use-header"));
                        foreach ($result as $item => $count) {
                            if ($count > 0) {
                                $player->sendMessage(str_replace(["{item}", "{count}"], [$item, $count], $config->getNested("farming-chest.message.farming-chest-use-format")));
                            }
                        }
                    } else {
                        $player->sendMessage($config->getNested("farming-chest.message.no-crops-found"));
                    }
                }
            }
        }, EventPriority::NORMAL, $this);

        $this->getLogger()->notice($config->getNested("farming-chest.message.enable-plugin"));
    }

    /**
     * @return void
     */
    protected function onDisable(): void {
        $this->getLogger()->notice($this->getConfig()->getNested("farming-chest.message.disable-plugin"));
    }

}
