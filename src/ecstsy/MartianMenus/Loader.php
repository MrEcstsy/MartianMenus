<?php

declare(strict_types=1);

namespace ecstsy\MartianMenus;

use CortexPE\Commando\BaseCommand;
use ecstsy\MartianMenus\utils\MenuManager;
use ecstsy\MartianUtilities\managers\LanguageManager;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
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

        $this->getServer()->getCommandMap()->register("MartianMenus", new class("menu") extends Command {
            public function __construct(string $name){
                parent::__construct($name);
                $this->setPermission("MartianMenus.menu");
            }

            public function execute(CommandSender $sender, string $commandLabel, array $args)
            {
                if (!$sender instanceof Player) return;

                Loader::getInstance()->getMenuManager()->openMenu("kit", $sender);
            }
        });
        
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