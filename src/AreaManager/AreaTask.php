<?php
namespace AreaManager;

use pocketmine\Player;
use pocketmine\scheduler\Task;

class AreaTask extends Task {
    public function __construct(AreaManager $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun($currentTick) {
        foreach ($this->plugin->tdata as $id => $value) {
            if (time() - $value >= 1296000) {
                $owner = $this->plugin->getOwner($id);
                $this->plugin->delArea($id);
                if (($player = $this->plugin->getServer()->getPlayer($owner)) instanceof Player) {
                    $player->sendMessage("{$this->plugin->pre} {$id}번 사유지가 2주간 변동이 없어 파기되었습니다.");
                }
            }
        }
    }
}
