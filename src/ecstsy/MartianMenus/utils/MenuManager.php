<?php

declare(strict_types=1);

namespace ecstsy\MartianMenus\utils;

use ecstsy\MartianMenus\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\InventoryUtils;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;

final class MenuManager {

    /** @var array<string, array> */
    private array $menus = [];

    private Plugin $plugin;

    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;

        $this->loadMenus();
    }

    public function loadMenus(): void {
        $menuFolder = $this->plugin->getDataFolder() . "menus/";
        if(!is_dir($menuFolder)){
            @mkdir($menuFolder);
        }
        foreach(scandir($menuFolder) as $file){
            if(pathinfo($file, PATHINFO_EXTENSION) === "yml"){
                $config = GeneralUtils::getConfiguration($this->plugin, "menus/" . $file);
                if($config !== null){
                    $menuName = pathinfo($file, PATHINFO_FILENAME);
                    $this->menus[$menuName] = $config->getAll();
                }
            }
        }
    }

    /**
     * @param string $name
     * @return array|null
     */
    public function getMenu(string $name): ?array {
        return $this->menus[$name] ?? null;
    }

    public function openMenu(string $menuName, Player $player): void {
        $menuConfig = $this->getMenu($menuName);
        if ($menuConfig === null) {
            $player->sendMessage(C::colorize(Loader::getLanguageManager()->getNested("menu.not-found")));
            return;
        }
        
        $sizeType = $menuConfig["settings"]["size"] ?? "CHEST";

        $invMenu = InvMenu::create($this->getMenuType($sizeType));
        $invMenu->setName(C::colorize($menuConfig["menu"]["title"] ?? "Martian Menu"));
        $inventory = $invMenu->getInventory();
        
        if (isset($menuConfig["menu"]["items"]["INVENTORY"])) {
            $invFill = $menuConfig["menu"]["items"]["INVENTORY"];
            $fillItem = StringToItemParser::getInstance()->parse($invFill["material"]);
            $fillItem->setCustomName(C::colorize($invFill["name"] ?? " "));
            InventoryUtils::fillInventory($inventory, $fillItem);
        }
        
        if (isset($menuConfig["menu"]["items"]["BORDER"])) {
            $borderFill = $menuConfig["menu"]["items"]["BORDER"];
            $borderItem = StringToItemParser::getInstance()->parse($borderFill["material"]);
            $borderItem->setCustomName(C::colorize($borderFill["name"] ?? " "));
            InventoryUtils::fillBorders($inventory, $borderItem);
        }
        
        foreach ($menuConfig["menu"]["items"] as $key => $itemConfig) {
            if (is_numeric($key)) {
                $slot = (int)$key;
                $item = StringToItemParser::getInstance()->parse($itemConfig["material"]);
                $item->setCustomName(C::colorize($itemConfig["name"] ?? ""));
                if (isset($itemConfig["lore"]) && is_array($itemConfig["lore"])) {
                    $lore = array_map([C::class, 'colorize'], $itemConfig["lore"]);
                    $item->setLore($lore);
                }
                $inventory->setItem($slot, $item);
            }
        }
        
        $invMenu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction) use ($menuConfig) {
            $player = $transaction->getPlayer();
            $slot = $transaction->getAction()->getSlot();

            if(isset($menuConfig["menu"]["items"][(string)$slot])) {
                $itemConfig = $menuConfig["menu"]["items"][(string)$slot];
        
                if(isset($itemConfig["permission"]) && !$player->hasPermission($itemConfig["permission"])) {
                    $player->sendMessage(C::colorize(Loader::getLanguageManager()->getNested("general.no-permission")));
                    return;
                }
                
                if(isset($itemConfig["commands"]) && is_array($itemConfig["commands"])) {
                    foreach($itemConfig["commands"] as $commandData) {
                        $this->executeCommand($player, $commandData);
                    }
                }
            }
        }));
        
        $invMenu->send($player);
    }

    private function executeCommand(Player $player, array $commandData): void {
        $type = strtoupper($commandData["type"]);
        switch ($type) {
            case "PLAYER":
                $cmd = str_replace(["{PLAYER}"], [$player->getName()], $commandData["command"]);
                $player->getServer()->dispatchCommand($player, $cmd);
                break;
            case "CONSOLE":
                $cmd = str_replace(["{PLAYER}"], [$player->getName()], $commandData["command"]);
                $player->getServer()->dispatchCommand(new ConsoleCommandSender(Server::getInstance(), Server::getInstance()->getLanguage()), $cmd);
                break;
            case "MESSAGE":
                $player->sendMessage(C::colorize($commandData["text"]));
                break;
        }
    }

    private function getMenuType(string $menuType): ?string {
        switch ($menuType) {
            case "CHEST":
                return InvMenuTypeIds::TYPE_CHEST;
            case "DOUBLE_CHEST":
                return InvMenuTypeIds::TYPE_DOUBLE_CHEST;
            case "HOPPER":
                return InvMenuTypeIds::TYPE_HOPPER;
        }

        return null;
    }
}