<?php
namespace AreaManager;

use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use ServerLogManager\ServerLogManager;
use TeleMoney\TeleMoney;
use UiLibrary\UiLibrary;

class AreaManager extends PluginBase {

    private static $instance = null;
    public $allowBlock = [31, 37, 38, 175];
    public $pre = "§e•";

    //public $pre = "§l§e[ §f사유지 §e]§r§e";

    public static function getInstance() {
        return self::$instance;
    }

    public function onLoad() {
        self::$instance = $this;
    }

    public function onEnable() {
        @mkdir($this->getDataFolder());
        $this->area = new Config($this->getDataFolder() . "area.yml", Config::YAML, ["MaxId" => 0, "Size" => 20, "MaxArea" => 1, "Prize" => 50000, "MaxHeight" => 50]);
        $this->block = new Config($this->getDataFolder() . "block.yml", Config::YAML);
        $this->owner = new Config($this->getDataFolder() . "owner.yml", Config::YAML);
        $this->time = new Config($this->getDataFolder() . "time.yml", Config::YAML);
        $this->adata = $this->area->getAll();
        $this->bdata = $this->block->getAll();
        $this->odata = $this->owner->getAll();
        $this->tdata = $this->time->getAll();
        $this->areaId = $this->adata["MaxId"];
        $this->money = TeleMoney::getInstance();
        $this->ui = UiLibrary::getInstance();
        $this->serverlog = ServerLogManager::getInstance();
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->getScheduler()->scheduleRepeatingTask(new AreaTask($this), 600 * 20);
    }

    public function onDisable() {
        $this->save();
    }

    public function save() {
        $this->area->setAll($this->adata);
        $this->area->save();
        $this->block->setAll($this->bdata);
        $this->block->save();
        $this->owner->setAll($this->odata);
        $this->owner->save();
        $this->time->setAll($this->tdata);
        $this->time->save();
    }

    public function isMyArea(string $name, int $x, int $z, string $level) {
        $name = mb_strtolower($name);
        if (!isset($this->bdata["block_1"]["{$x}:{$z}:{$level}"]))
            return false;
        $id = $this->getAreaId($x, $z, $level);
        return in_array($name, $this->adata["area"][$id]["visitor"]);
    }

    public function getAreaId(int $x, int $z, string $level) {
        if ($this->isArea($x, $z, $level))
            return $this->bdata["block_1"]["{$x}:{$z}:{$level}"];
        else
            return null;
    }

    public function isArea(int $x, int $z, string $level) {
        return isset($this->bdata["block_1"]["{$x}:{$z}:{$level}"]);
    }

    public function getMessage(int $id) {
        if (!isset($this->adata["area"][$id]))
            return null;
        else
            return $this->adata["area"][$id]["message"];
    }

    public function AreaUI(Player $player) {
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
            if (!isset($data[0])) return;

            if ($data[0] == 0) {
                if ($this->money->getMoney($player->getName()) < $this->adata["Prize"]) {
                    $player->sendMessage("{$this->pre} 사유지를 선언할 테나가 부족합니다.");
                    return false;
                }
                $start_x = $player->getFloorX() - 10;
                $start_z = $player->getFloorZ() - 10;
                $end_x = $player->getFloorX() + 10;
                $end_z = $player->getFloorZ() + 10;
                if ($this->addArea($player->getName(), (int) $player->getFloorY() - 1, (int) $start_x, (int) $start_z, (int) $end_x, (int) $end_z, $player->getLevel())) {
                    $this->money->reduceMoney($player->getName(), $this->adata["Prize"]);
                    $player->sendMessage("{$this->pre} 반경 {$this->adata["Size"]}칸을 사유지로 선언하였습니다.");
                    $this->save();
                    return true;
                } else {
                    $player->sendMessage("{$this->pre} 사유지 선언에 실패하였습니다.");
                    return false;
                }
            }

            if ($data[0] == 1) {
                $x = $player->getFloorX();
                $z = $player->getFloorZ();
                $world = $player->getLevel()->getFolderName();
                if (!$player->isOp() && !$this->isOwner($player->getName(), $x, $z, $world)) {
                    $player->sendMessage("{$this->pre} 해당 위치의 사유지는 본인의 사유지가 아닙니다.");
                    return false;
                } else {
                    $id = $this->getAreaId($x, $z, $world);
                    $this->delArea($id);
                    $player->sendMessage("{$this->pre} 본인 사유지를 제거하였습니다.");
                    $this->save();
                }
            }

            if ($data[0] == 2) {
                if ($this->getAreas($player->getName()) == null || count($this->getAreas($player->getName())) == 0) {
                    $player->sendMessage("{$this->pre} 본인의 사유지를 찾아볼 수 없습니다.");
                    return false;
                }
                $this->ShowAreaList($player);
                return true;
            }

            if ($data[0] == 3) {
                $x = $player->getFloorX();
                $z = $player->getFloorZ();
                $world = $player->getLevel()->getFolderName();
                if (!$this->isArea($x, $z, $world)) {
                    $player->sendMessage("{$this->pre} 해당 위치의 사유지를 찾아볼 수 없습니다.");
                    return false;
                }
                $this->ShowAreaInfo($player, $this->getAreaId($x, $z, $world));
                return true;
            }

            if ($data[0] == 4) {
                $x = $player->getFloorX();
                $z = $player->getFloorZ();
                $world = $player->getLevel()->getFolderName();
                if (!$this->isArea($x, $z, $world)) {
                    $player->sendMessage("{$this->pre} 해당 위치의 사유지를 찾아볼 수 없습니다.");
                    return false;
                }
                if (!$player->isOp() && !$this->isOwner($player->getName(), $x, $z, $world)) {
                    $player->sendMessage("{$this->pre} 해당 위치의 사유지는 본인의 사유지가 아닙니다.");
                    return false;
                }
                $this->ManageArea($player);
                return true;
            }
        });
        $form->setTitle("Tele Area");
        $form->setContent("§l§a▶ §r§f사유지 선언 가능 기준\n  §f- 주변에 타인의 사유지가 없어야함.\n  §f- 지면이 잔디이며, 평지여야함.\n  §f- 주변에 장애물이 존재하지 않아야함.");
        $form->addButton("§l사유지 선언\n§r§8본인의 위치에 사유지를 선언합니다.");
        $form->addButton("§l사유지 파기\n§r§8본인의 사유지를 파기합니다. (사유지의 블럭은 소실)");
        $form->addButton("§l사유지 목록\n§r§8본인의 사유지를 확인합니다.");
        $form->addButton("§l사유지 정보\n§r§8해당 위치의 사유지 정보를 확인합니다.");
        $form->addButton("§l사유지 관리\n§r§8본인의 사유지를 관리합니다.");
        $form->addButton("§l닫기");
        $form->sendToPlayer($player);
    }

    public function addArea(string $owner, int $bottom, int $start_x, int $start_z, int $end_x, int $end_z, Level $level) {
        $owner = mb_strtolower($owner);
        if (isset($this->odata["id"][$owner]) && count($this->odata["id"][$owner]) >= $this->adata["MaxArea"])
            return false;
        for ($x = $start_x - 5; $x <= $end_x + 5; $x++) {
            for ($z = $start_z - 5; $z <= $end_z + 5; $z++) {
                if ($this->isArea($x, $z, $level->getFolderName())) {
                    $isset = true;
                    break;
                }
                $block = $level->getBlock(new Vector3($x, $bottom, $z));
                if ($block->getId() !== 2) {
                    $isset = true;
                    break;
                }
                for ($y = $bottom + 1; $y <= $bottom + 50; $y++) {
                    if ($level->getBlock(new Vector3($x, $y, $z))->getId() !== 0 && !in_array($level->getBlock(new Vector3($x, $y, $z))->getId(), $this->allowBlock)) {
                        $isset = true;
                        break;
                    }
                }
            }
        }
        if (isset($isset)) {
            return false;
        } else {
            $id = $this->areaId++;
            $this->adata["area"][$id]["id"] = $id;
            $this->adata["area"][$id]["owner"] = $owner;
            $this->adata["area"][$id]["visitor"] = [];
            $this->adata["area"][$id]["visitor"][] = $owner;
            $this->adata["area"][$id]["message"] = "환영말을 설정해주세요.";
            $this->adata["area"][$id]["access"] = true;
            $this->adata["area"][$id]["start_x"] = $start_x;
            $this->adata["area"][$id]["start_z"] = $start_z;
            $this->adata["area"][$id]["end_x"] = $end_x;
            $this->adata["area"][$id]["end_z"] = $end_z;
            $this->adata["area"][$id]["bottom"] = $bottom;
            $this->adata["area"][$id]["level"] = $level->getFolderName();

            for ($x = $start_x; $x <= $end_x; $x++) {
                for ($z = $start_z; $z <= $end_z; $z++) {
                    $this->bdata["block_1"]["{$x}:{$z}:{$level->getFolderName()}"] = $id;
                }
            }
            $this->odata["owner"][$id] = $owner;
            if (!isset($this->odata["id"][$owner]))
                $this->odata["id"][$owner] = [];
            $this->odata["id"][$owner][] = $id;
            $this->setFence($bottom, $start_x, $start_z, $end_x, $end_z, $level);
            $this->tdata[$id] = time();
            $this->adata["MaxId"] = $id;
            return true;
        }
    }

    private function setFence(int $bottom, int $start_x, int $start_z, int $end_x, int $end_z, Level $level) {
        $count = 1;
        for ($x = $start_x; $x <= $end_x; $x++) {
            if ($count % 4 !== 0) {
                $level->setBlock(new Vector3($x, $bottom, $start_z), Block::get(98, 0));
                $level->setBlock(new Vector3($end_x - $count + 1, $bottom, $end_z), Block::get(98, 0));
            }
            $count++;
        }
        $count = 1;
        for ($z = $start_z; $z <= $end_z; $z++) {
            if ($count % 4 !== 0) {
                $level->setBlock(new Vector3($start_x, $bottom, $z), Block::get(98, 0));
                $level->setBlock(new Vector3($end_x, $bottom, $end_z - $count + 1), Block::get(98, 0));
            }
            $count++;
        }
    }

    public function isOwner(string $name, int $x, int $z, string $level) {
        $name = mb_strtolower($name);
        if (!isset($this->bdata["block_1"]["{$x}:{$z}:{$level}"]))
            return false;
        $id = $this->getAreaId($x, $z, $level);
        return $name == mb_strtolower($this->getOwner($id));
    }

    public function delArea(int $id) {
        for ($x = $this->getStart_X($id); $x <= $this->getEnd_X($id); $x++) {
            for ($z = $this->getStart_Z($id); $z <= $this->getEnd_Z($id); $z++) {
                unset($this->bdata["block_1"]["{$x}:{$z}:{$this->getWorld($id)->getFolderName()}"]);
                for ($y = $this->getBottom($id); $y <= $this->getBottom($id) + 50; $y++) {
                    if ($y == $this->getBottom($id)) {
                        $this->getWorld($id)->setBlock(new Vector3($x, $y, $z), Block::get(2, 0));
                    } else {
                        $this->getWorld($id)->setBlock(new Vector3($x, $y, $z), Block::get(0, 0));
                    }
                }
            }
        }
        foreach ($this->bdata["block_1"] as $key => $value) {
            if ($value == $id) {
                unset($this->bdata[$key]);
                $isset = true;
            }
        }
        if (isset($isset)) {
            $this->getServer()->getLogger()->notice("{$this->pre} 사유지 삭제오류 발생");
        }
        unset($this->adata["area"][$id]);
        unset($this->odata["id"][$this->getOwner($id)][array_search($id, $this->odata["id"][$this->getOwner($id)])]);
        unset($this->odata["owner"][$id]);
        unset($this->tdata[$id]);
    }

    public function getStart_X(int $id) {
        if (!isset($this->adata["area"][$id]))
            return null;
        else
            return (int) $this->adata["area"][$id]["start_x"];
    }

    public function getEnd_X(int $id) {
        if (!isset($this->adata["area"][$id]))
            return null;
        else
            return (int) $this->adata["area"][$id]["end_x"];
    }

    public function getStart_Z(int $id) {
        if (!isset($this->adata["area"][$id]))
            return null;
        else
            return (int) $this->adata["area"][$id]["start_z"];
    }

    public function getEnd_Z(int $id) {
        if (!isset($this->adata["area"][$id]))
            return null;
        else
            return (int) $this->adata["area"][$id]["end_z"];
    }

    public function getWorld(int $id) {
        if (!isset($this->adata["area"][$id]))
            return null;
        else
            return $this->getServer()->getLevelByName($this->adata["area"][$id]["level"]);
    }

    public function getBottom(int $id) {
        if (!isset($this->adata["area"][$id]))
            return null;
        else
            return $this->adata["area"][$id]["bottom"];
    }

    public function getAreas(string $name) {
        $name = mb_strtolower($name);
        if (!isset($this->odata["id"][$name]))
            return null;
        else
            return $this->odata["id"][$name];
    }

    private function ShowAreaList(Player $player) {
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
        });
        $form->setTitle("Tele Area");
        $list = "";
        foreach ($this->getAreas($player->getName()) as $key => $id) {
            $x = $this->getStart_X($id) + 10;
            $z = $this->getStart_Z($id) + 10;
            $list .= "§l§a▶ §r§f{$id}번 사유지 (x => {$x}, z => {$z})\n";
        }
        $form->setContent($list);
        $form->sendToPlayer($player);
    }

    private function ShowAreaInfo(Player $player, int $id) {
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
        });
        $form->setTitle("Tele Area");
        $list = "";
        $x = $this->getStart_X($id) + 10;
        $z = $this->getStart_Z($id) + 10;
        $list .= "§l§a▶ §r§f{$id}번 사유지 (x => {$x}, z => {$z})\n\n";
        $list .= "§l§a▶ §r§f거주자 목록\n";
        foreach ($this->getVisitors($id) as $key => $name) {
            $list .= "  §f- {$name}\n";
        }
        if ($this->isAccess($id) == true) {
            $is = "허용";
        } else {
            $is = "거부";
        }
        $list .= "\n";
        $list .= "§l§a▶ §r§f사유지 출입 가능 유무: {$is}";
        $form->setContent($list);
        $form->sendToPlayer($player);
    }

    public function getVisitors(int $id) {
        if (!isset($this->adata["area"][$id]))
            return null;
        else
            return $this->adata["area"][$id]["visitor"];
    }

    public function isAccess(int $id) {
        if (!isset($this->adata["area"][$id]))
            return true;
        else {
            return $this->adata["area"][$id]["access"];
        }
    }

    private function ManageArea(Player $player) {
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
            if (!isset($data[0])) return;
            if ($data[0] == 0) {
                $this->setting($player, "초대");
            }
            if ($data[0] == 1) {
                $this->setting($player, "추방");
            }
            if ($data[0] == 2) {
                $this->setting($player, "환영말");
            }
            if ($data[0] == 3) {
                $x = $player->getFloorX();
                $z = $player->getFloorZ();
                $world = $player->getLevel()->getFolderName();
                $id = $this->getAreaId($x, $z, $world);
                $is = $this->setAccess($id);
                $player->sendMessage("{$this->pre} 사유지 출입을 {$is}하였습니다.");
                $this->save();
            }
        });
        $form->setTitle("Tele Area");
        $form->addButton("§l거주자 초대\n§r§8본인 사유지에 거주자를 초대합니다.");
        $form->addButton("§l거주자 추방\n§r§8본인 사유지의 거주자를 추방합니다.");
        $form->addButton("§l환영말 설정\n§r§8본인 사유지의 환영말을 설정합니다.");
        $form->addButton("§l출입 거부 설정\n§r§8본인 사유지의 출입 가능 유무를 설정합니다.");
        $form->addButton("§l닫기");
        $form->sendToPlayer($player);
    }

    private function setting(Player $player, string $type) {
        $this->type[$player->getName()] = $type;
        $x = $player->getFloorX();
        $z = $player->getFloorZ();
        $world = $player->getLevel()->getFolderName();
        $id = $this->getAreaId($x, $z, $world);
        $form = $this->ui->CustomForm(function (Player $player, array $data) {
            if (!isset($data[0])) return;
            else {
                $x = $player->getFloorX();
                $z = $player->getFloorZ();
                $world = $player->getLevel()->getFolderName();
                $type = $this->type[$player->getName()];
                $id = $this->getAreaId($x, $z, $world);
                if ($type == "초대") {
                    if ($this->addVisitor($data[0], $id)) {
                        $player->sendMessage("{$this->pre} {$data[0]}님을 사유지에 초대하였습니다.");
                        $this->save();
                        unset($this->type[$player->getName()]);
                        return true;
                    } else {
                        $player->sendMessage("{$this->pre} 사유지 초대에 실패하였습니다.");
                        unset($this->type[$player->getName()]);
                        return false;
                    }
                } elseif ($type == "추방") {
                    if ($this->delVisitor($data[0], $id)) {
                        $player->sendMessage("{$this->pre} {$data[0]}님을 사유지에서 추방하였습니다.");
                        $this->save();
                        unset($this->type[$player->getName()]);
                        return true;
                    } else {
                        $player->sendMessage("{$this->pre} 거주자 추방에 실패하였습니다.");
                        unset($this->type[$player->getName()]);
                        return false;
                    }
                } elseif ($type == "환영말") {
                    if ($this->setMessage($data[0], $id)) {
                        $player->sendMessage("{$this->pre} [ §r{$data[0]} §r§e] (을)를 환영말로 설정하였습니다.");
                        $this->save();
                        unset($this->type[$player->getName()]);
                        return true;
                    } else {
                        $player->sendMessage("{$this->pre} 환영말 설정에 실패하였습니다.");
                        unset($this->type[$player->getName()]);
                        return false;
                    }
                }
            }
        });
        $form->setTitle("Tele Area");
        if ($type == "초대") $text = "거주자를 초대합니다.";
        elseif ($type == "추방") $text = "거주자를 추방합니다.";
        elseif ($type == "환영말") $text = "환영말을 설정합니다.";
        $form->addInput("§l§a▶ §r§f{$text}", "닉네임");
        if ($type == "초대" || $type == "추방") {
            $list = "§l§a▶ §r§f거주자 목록\n";
            foreach ($this->getVisitors($id) as $key => $name) {
                $list .= "  §f- {$name}\n";
            }
            $form->addLabel($list);
        }
        $form->sendToPlayer($player);
    }

    public function addVisitor(string $name, int $id) {
        $name = mb_strtolower($name);
        if (!isset($this->adata["area"][$id]) || in_array($name, $this->adata["area"][$id]["visitor"]))
            return false;
        else {
            $this->adata["area"][$id]["visitor"][] = $name;
            return true;
        }
    }

    public function delVisitor(string $name, int $id) {
        $name = mb_strtolower($name);
        if (!isset($this->adata["area"][$id]) || !in_array($name, $this->adata["area"][$id]["visitor"]) || $name == $this->getOwner($id))
            return false;
        else {
            unset($this->adata["area"][$id]["visitor"][array_search($name, $this->adata["area"][$id]["visitor"])]);
            return true;
        }
    }

    public function getOwner(int $id) {
        if (!isset($this->odata["owner"][$id]))
            return null;
        else
            return $this->odata["owner"][$id];
    }

    public function setMessage(string $text, int $id) {
        if (!isset($this->adata["area"][$id]))
            return false;
        else {
            $this->adata["area"][$id]["message"] = $text;
            return true;
        }
    }

    public function setAccess(int $id) {
        if (!isset($this->adata["area"][$id]))
            return null;
        else {
            if ($this->adata["area"][$id]["access"] == true) {
                $this->adata["area"][$id]["access"] = false;
                return "거부";
            } else {
                $this->adata["area"][$id]["access"] = true;
                return "허용";
            }
        }
    }
}
