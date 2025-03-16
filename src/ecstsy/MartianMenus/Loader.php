<?php

declare(strict_types=1);

namespace ecstsy\MartianMenus;

use CortexPE\Commando\BaseCommand;
use ecstsy\MartianMenus\utils\MenuManager;
use ecstsy\MartianUtilities\managers\LanguageManager;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\utils\TextFormat as C;
use pocketmine\command\CommandSender;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;

final class Loader extends PluginBase {

    use SingletonTrait;

    public static MenuManager $menuManager;
    
    public static LanguageManager $languageManager;

    public function onLoad(): void {
        self::setInstance($this);
    }

    public function onEnable(): void {
        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }

        $this->saveDefaultConfig();

        $this->saveAllFilesInDirectory("menus");
        $this->saveAllFilesInDirectory("locale");
        
        self::$menuManager = new MenuManager($this);

        $config = GeneralUtils::getConfiguration($this, "config.yml");
        $language = $config->getNested("settings.language");

        self::$languageManager = new LanguageManager($this, $language);

        foreach ($this->getMenuManager()->getAllMenus() as $menuName => $menuConfig) {
            if (isset($menuConfig["settings"]["command"]["enabled"]) && $menuConfig["settings"]["command"]["enabled"] === true) {
                $commandName = $menuConfig["settings"]["command"]["name"] ?? $menuName;
                $permission = $menuConfig["settings"]["command"]["permission"];

                PermissionManager::getInstance()->addPermission(new Permission($permission, "Permission to run {$commandName}"));

                $this->getServer()->getCommandMap()->register("MartianMenus", new class($menuName, $commandName) extends BaseCommand {
                    
                    public function __construct(private string $menuName, string $commandName) {
                        parent::__construct(Loader::getInstance(), $commandName, "Opens the {$menuName} menu", []);
                    }

                    /** @var string|null */
                    private ?string $permission = null; 

                    public function prepare(): void {
                        $menuConfig = Loader::getMenuManager()->getMenu($this->menuName);
                        if ($menuConfig !== null && isset($menuConfig["settings"]["command"]["permission"])) {
                            $this->permission = $menuConfig["settings"]["command"]["permission"];
                        }

                        $this->setPermission($this->getPermission());                        
                    }
                    
                    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
                        if (!$sender instanceof Player) {
                            $sender->sendMessage(C::colorize(Loader::getLanguageManager()->getNested("general.non-player")));
                            return;
                        }
                        
                        if ($this->permission !== null && !$sender->hasPermission($this->permission)) {
                            $sender->sendMessage(C::colorize(Loader::getLanguageManager()->getNested("general.no-permission")));
                            return;
                        }
                        
                        Loader::getMenuManager()->openMenu($this->menuName, $sender);
                    }

                    public function getPermission(): string {
                        return $this->permission;
                    }
                });
                
                $this->getLogger()->info(C::GREEN . "Registered menu command: /" . $commandName . " for menu " . $menuName);
            }
        }
    }

    public static function getMenuManager(): MenuManager {
        return self::$menuManager;
    }

    public static function getLanguageManager(): LanguageManager {
        return self::$languageManager;
    }

    private function saveAllFilesInDirectory(string $directory): void {
        $resourcePath = $this->getFile() . "resources/$directory/";
        if (!is_dir($resourcePath)) {
            $this->getLogger()->warning("Directory $directory does not exist.");
            return;
        }

        $files = scandir($resourcePath);
        if ($files === false) {
            $this->getLogger()->warning("Failed to read directory $directory.");
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $this->saveResource("$directory/$file");
        }
    }
}
