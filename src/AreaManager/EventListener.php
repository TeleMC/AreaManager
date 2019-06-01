<?php

namespace AreaManager;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\Server;

class EventListener implements Listener {

    public function __construct(AreaManager $plugin) {
        $this->plugin = $plugin;
    }

    public function onMove(PlayerMoveEvent $ev) {
        $player = $ev->getPlayer();
        if ($this->plugin->isArea($player->getFloorX(), $player->getFloorZ(), $player->getLevel()->getFolderName())) {
            $id = $this->plugin->getAreaId($player->getFloorX(), $player->getFloorZ(), $player->getLevel()->getFolderName());
            if (!$this->plugin->isAccess($id) && !$player->isOp() && !$this->plugin->isMyArea($player->getName(), $player->getFloorX(), $player->getFloorZ(), $player->getLevel()->getFolderName())) {
                $id = $this->plugin->getAreaId($player->getFloorX(), $player->getFloorZ(), $player->getLevel()->getFolderName());
                $player->sendPopup("{$this->plugin->pre} 해당 사유지는 출입이 거부되어있습니다.");
                $player->teleport(new Vector3($this->plugin->getStart_X($id) - 2, $player->getY(), $this->plugin->getStart_Z($id) - 2));
                return false;
            }
            if (!isset($this->area[$player->getName()])) {
                $this->area[$player->getName()] = true;
                $message = $this->plugin->getMessage($id);
                $owner = $this->plugin->getOwner($id);
                if ($this->plugin->isMyArea($player->getName(), $player->getFloorX(), $player->getFloorZ(), $player->getLevel()->getFolderName()) && !$player->isOp())
                    $player->setGamemode(0);
                $player->setAllowFlight(true);
                $player->sendPopup("{$this->plugin->pre} 이곳은 {$owner}님의 사유지입니다.\n§r§f{$message}");
                return true;
            }
        } elseif (isset($this->area[$player->getName()])) {
            unset($this->area[$player->getName()]);
            if (!$player->isOp() && $player->getGamemode() !== 2)
                $player->setGamemode(2);
            $player->setAllowFlight(true);
            return true;
        } elseif (!isset($this->area[$player->getName()])) {
            if (!$player->isOp() && $player->getGamemode() !== 2)
                $player->setGamemode(2);
            $player->setAllowFlight(true);
            return true;
        }
    }

    public function onBreak(BlockBreakEvent $ev) {
        if (!$ev->getPlayer()->isOp()) {
            if ($ev->isCancelled())
                return;
            $block = $ev->getBlock();
            if (!$this->plugin->isMyArea($ev->getPlayer()->getName(), $block->getX(), $block->getZ(), $block->getLevel()->getFolderName())) {
                $ev->setCancelled(true);
            } else {
                $id = $this->plugin->getAreaId($block->getX(), $block->getZ(), $block->getLevel()->getFolderName());
                if ($block->getY() < $this->plugin->getBottom($id) + 1 || $this->plugin->getBottom($id) + 30 < $block->getY()) {
                    $ev->setCancelled(true);
                    return;
                }
                $this->plugin->serverlog->addBlockLog($ev->getPlayer()->getName(), $block, 0, "파괴");
                $this->plugin->tdata[$id] = time();
            }
        }
    }

    public function onPlace(BlockPlaceEvent $ev) {
        if (!$ev->getPlayer()->isOp()) {
            if ($ev->isCancelled())
                return;
            $block = $ev->getBlock();
            if (!$this->plugin->isMyArea($ev->getPlayer()->getName(), $block->getX(), $block->getZ(), $block->getLevel()->getFolderName())) {
                $ev->setCancelled(true);
            } else {
                $id = $this->plugin->getAreaId($block->getX(), $block->getZ(), $block->getLevel()->getFolderName());
                if ($block->getY() < $this->plugin->getBottom($id) + 1 || $this->plugin->getBottom($id) + 30 < $block->getY()) {
                    $ev->setCancelled(true);
                    return;
                }
                $this->plugin->serverlog->addBlockLog($ev->getPlayer()->getName(), $block, 0, "설치");
                $this->plugin->tdata[$id] = time();
            }
        }
    }

    public function onTouch(PlayerInteractEvent $ev) {
        if (!$ev->getPlayer()->isOp()) {
            if ($ev->isCancelled())
                return;
            $block = $ev->getBlock();
            if (!$this->plugin->isMyArea($ev->getPlayer()->getName(), $block->getX(), $block->getZ(), $block->getLevel()->getFolderName())) {
                $ev->setCancelled(true);
            } else {
                $id = $this->plugin->getAreaId($block->getX(), $block->getZ(), $block->getLevel()->getFolderName());
                if ($block->getY() < $this->plugin->getBottom($id) || $this->plugin->getBottom($id) + 50 < $block->getY()) {
                    $ev->setCancelled(true);
                    return;
                }
                $this->plugin->tdata[$id] = time();
            }
        }
        //if($block->getId() === Block::SIGN_POST || $block->getId() === Block::WALL_SIGN || $block->getId() === Block::ITEM_FRAME_BLOCK)
    }

}
