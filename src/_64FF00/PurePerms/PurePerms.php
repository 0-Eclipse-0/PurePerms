<?php

namespace _64FF00\PurePerms;

use _64FF00\PurePerms\cmd\AddGroup;
use _64FF00\PurePerms\cmd\AddParent;
use _64FF00\PurePerms\cmd\DefGroup;
use _64FF00\PurePerms\cmd\FPerms;
use _64FF00\PurePerms\cmd\Groups;
use _64FF00\PurePerms\cmd\ListGPerms;
use _64FF00\PurePerms\cmd\ListUPerms;
use _64FF00\PurePerms\cmd\PPInfo;
use _64FF00\PurePerms\cmd\PPReload;
use _64FF00\PurePerms\cmd\PPSudo;
use _64FF00\PurePerms\cmd\RmGroup;
use _64FF00\PurePerms\cmd\RmParent;
use _64FF00\PurePerms\cmd\SetGPerm;
use _64FF00\PurePerms\cmd\SetGroup;
use _64FF00\PurePerms\cmd\SetUPerm;
use _64FF00\PurePerms\cmd\UnsetGPerm;
use _64FF00\PurePerms\cmd\UnsetUPerm;
use _64FF00\PurePerms\cmd\UsrInfo;
use _64FF00\PurePerms\data\UserDataManager;
use _64FF00\PurePerms\noeul\NoeulAPI;
use _64FF00\PurePerms\provider\DefaultProvider;
use _64FF00\PurePerms\provider\MySQLProvider;
use _64FF00\PurePerms\provider\ProviderInterface;

use pocketmine\IPlayer;

use pocketmine\level\Level;

use pocketmine\permission\DefaultPermissions;

use pocketmine\Player;

use pocketmine\plugin\PluginBase;

use pocketmine\utils\UUID;

class PurePerms extends PluginBase
{
    /*
        PurePerms by 64FF00 (Twitter: @64FF00)

          888  888    .d8888b.      d8888  8888888888 8888888888 .d8888b.   .d8888b.
          888  888   d88P  Y88b    d8P888  888        888       d88P  Y88b d88P  Y88b
        888888888888 888          d8P 888  888        888       888    888 888    888
          888  888   888d888b.   d8P  888  8888888    8888888   888    888 888    888
          888  888   888P "Y88b d88   888  888        888       888    888 888    888
        888888888888 888    888 8888888888 888        888       888    888 888    888
          888  888   Y88b  d88P       888  888        888       Y88b  d88P Y88b  d88P
          888  888    "Y8888P"        888  888        888        "Y8888P"   "Y8888P"
    */

    const MAIN_PREFIX = "\x5b\x50\x75\x72\x65\x50\x65\x72\x6d\x73\x3a\x36\x34\x46\x46\x30\x30\x5d";

    const CORE_PERM = "\x70\x70\x65\x72\x6d\x73\x2e\x63\x6f\x6d\x6d\x61\x6e\x64\x2e\x70\x70\x69\x6e\x66\x6f";

    const NOT_FOUND = null;
    const INVALID_NAME = -1;
    const ALREADY_EXISTS = 0;
    const SUCCESS = 1;

    private $isGroupsLoaded = false;

    /** @var PPMessages $messages */
    private $messages;

    /** @var NoeulAPI $noeulAPI */
    private $noeulAPI;

    /** @var ProviderInterface $provider */
    private $provider;

    /** @var UserDataManager $userDataMgr */
    private $userDataMgr;

    private $attachments = [], $groups = [], $pmDefaultPerms = [];

    public function onLoad()
    {
        $this->saveDefaultConfig();

        $this->messages = new PPMessages($this);

        $this->noeulAPI = new NoeulAPI($this);

        $this->userDataMgr = new UserDataManager($this);

        if($this->getConfigValue("enable-multiworld-perms") === false)
        {
            $this->getLogger()->notice($this->getMessage("logger_messages.onEnable_01"));
            $this->getLogger()->notice($this->getMessage("logger_messages.onEnable_02"));
        }
        else
        {
            $this->getLogger()->notice($this->getMessage("logger_messages.onEnable_03"));
        }
    }
    
    public function onEnable()
    {
        $this->registerCommands();

        $this->setProvider();

        $this->registerPlayers();

        $this->getServer()->getPluginManager()->registerEvents(new PPListener($this), $this);
    }

    public function onDisable()
    {
        $this->unregisterPlayers();

        if($this->isValidProvider())
            $this->provider->close();
    }

    private function registerCommands()
    {
        $commandMap = $this->getServer()->getCommandMap();

        if($this->getNoeulAPI()->isNoeulEnabled())
            $commandMap->register("ppsudo", new PPSudo($this, "ppsudo", $this->getMessage("cmds.ppsudo.desc") . ' #64FF00'));

        $commandMap->register("addgroup", new AddGroup($this, "addgroup", $this->getMessage("cmds.addgroup.desc") . ' #64FF00'));
        $commandMap->register("addparent", new AddParent($this, "addparent", $this->getMessage("cmds.addparent.desc") . ' #64FF00'));
        $commandMap->register("defgroup", new DefGroup($this, "defgroup", $this->getMessage("cmds.defgroup.desc") . ' #64FF00'));
        $commandMap->register("fperms", new FPerms($this, "fperms", $this->getMessage("cmds.fperms.desc") . ' #64FF00'));
        $commandMap->register("groups", new Groups($this, "groups", $this->getMessage("cmds.groups.desc") . ' #64FF00'));
        $commandMap->register("listgperms", new ListGPerms($this, "listgperms", $this->getMessage("cmds.listgperms.desc") . ' #64FF00'));
        $commandMap->register("listuperms", new ListUPerms($this, "listuperms", $this->getMessage("cmds.listuperms.desc") . ' #64FF00'));
        $commandMap->register("ppinfo", new PPInfo($this, "ppinfo", $this->getMessage("cmds.ppinfo.desc") . ' #64FF00'));
        $commandMap->register("ppreload", new PPReload($this, "ppreload", $this->getMessage("cmds.ppreload.desc") . ' #64FF00'));
        $commandMap->register("rmgroup", new RmGroup($this, "rmgroup", $this->getMessage("cmds.rmgroup.desc") . ' #64FF00'));
        $commandMap->register("rmparent", new RmParent($this, "rmparent", $this->getMessage("cmds.rmparent.desc") . ' #64FF00'));
        $commandMap->register("setgperm", new SetGPerm($this, "setgperm", $this->getMessage("cmds.setgperm.desc") . ' #64FF00'));
        $commandMap->register("setgroup", new SetGroup($this, "setgroup", $this->getMessage("cmds.setgroup.desc") . ' #64FF00'));
        $commandMap->register("setuperm", new SetUPerm($this, "setuperm", $this->getMessage("cmds.setuperm.desc") . ' #64FF00'));
        $commandMap->register("unsetgperm", new UnsetGPerm($this, "unsetgperm", $this->getMessage("cmds.unsetgperm.desc") . ' #64FF00'));
        $commandMap->register("unsetuperm", new UnsetUPerm($this, "unsetuperm", $this->getMessage("cmds.unsetuperm.desc") . ' #64FF00'));
        $commandMap->register("usrinfo", new UsrInfo($this, "usrinfo", $this->getMessage("cmds.usrinfo.desc") . ' #64FF00'));

    }

    /**
     * @param bool $onEnable
     */
    private function setProvider($onEnable = true)
    {
        $providerName = $this->getConfigValue("data-provider");

        switch(strtolower($providerName))
        {
            case "mysql":

                $provider = new MySQLProvider($this);

                if($onEnable === true)
                    $this->getLogger()->info($this->getMessage("logger_messages.setProvider_MySQL"));

                break;

            case "yaml":

                $provider = new DefaultProvider($this);

                if($onEnable === true)
                    $this->getLogger()->info($this->getMessage("logger_messages.setProvider_YAML"));

                break;

            default:

                $provider = new DefaultProvider($this);

                if($onEnable === true)
                    $this->getLogger()->warning($this->getMessage("logger_messages.setProvider_NotFound", "'$providerName'"));

                break;
        }

        if(!$this->isValidProvider())
            $this->provider = $provider;

        $this->updateGroups();
    }

    /*
          888  888          d8888 8888888b. 8888888
          888  888         d88888 888   Y88b  888
        888888888888      d88P888 888    888  888
          888  888       d88P 888 888   d88P  888
          888  888      d88P  888 8888888P"   888
        888888888888   d88P   888 888         888
          888  888    d8888888888 888         888
          888  888   d88P     888 888       8888888
    */

    /**
     * @param $groupName
     * @return bool
     */
    public function addGroup($groupName)
    {
        $groupsData = $this->getProvider()->getGroupsData();

        if(!$this->isValidGroupName($groupName))
            return self::INVALID_NAME;

        if(isset($groupsData[$groupName]))
            return self::ALREADY_EXISTS;

        $groupsData[$groupName] = [
            "isDefault" => false,
            "inheritance" => [
            ],
            "permissions" => [
            ],
            "worlds" => [
            ]
        ];

        $this->getProvider()->setGroupsData($groupsData);

        $this->updateGroups();

        return self::SUCCESS;
    }

    /**
     * @param Player $player
     * @return null|\pocketmine\permission\PermissionAttachment
     */
    public function getAttachment(Player $player)
    {
        $uniqueId = $this->getValidUUID($player);

        if(!isset($this->attachments[$uniqueId]))
            throw new \RuntimeException("Tried to calculate permissions on " .  $player->getName() . " using null attachment");

        return $this->attachments[$uniqueId];
    }

    /**
     * @param $key
     * @return null
     */
    public function getConfigValue($key)
    {
        $value = $this->getConfig()->getNested($key);

        if($value === null)
        {
            $this->getLogger()->warning($this->getMessage("logger_messages.getConfigValue_01", $key));

            return null;
        }

        return $value;
    }

    /**
     * @return PPGroup|null
     */
    public function getDefaultGroup()
    {
        $defaultGroups = [];

        foreach($this->getGroups() as $defaultGroup)
        {
            if($defaultGroup->isDefault())
                $defaultGroups[] = $defaultGroup;
        }

        if(count($defaultGroups) === 1)
        {
            return $defaultGroups[0];
        }
        else
        {
            if(count($defaultGroups) > 1)
            {
                $this->getLogger()->warning($this->getMessage("logger_messages.getDefaultGroup_01"));
            }
            elseif(count($defaultGroups) <= 0)
            {
                $this->getLogger()->warning($this->getMessage("logger_messages.getDefaultGroup_02"));

                $defaultGroups = $this->getGroups();
            }

            $this->getLogger()->info($this->getMessage("logger_messages.getDefaultGroup_03"));

            foreach($defaultGroups as $defaultGroup)
            {
                if(count($defaultGroup->getParentGroups()) === 0)
                {
                    $this->setDefaultGroup($defaultGroup);

                    return $defaultGroup;
                }
            }
        }

        return null;
    }

    /**
     * @param $groupName
     * @return PPGroup|null
     */
    public function getGroup($groupName)
    {
        if(!isset($this->groups[$groupName]))
        {
            $this->getLogger()->debug($this->getMessage("logger_messages.getGroup_01", $groupName));

            return null;
        }

        /** @var PPGroup $group */
        $group = $this->groups[$groupName];

        if(empty($group->getData()))
        {
            $this->getLogger()->warning($this->getMessage("logger_messages.getGroup_02", $groupName));

            return null;
        }

        return $group;
    }

    /**
     * @return PPGroup[]
     */
    public function getGroups()
    {
        if($this->isGroupsLoaded != true)
            throw new \RuntimeException("No groups loaded, maybe a provider error?");

        return $this->groups;
    }

    /**
     * @param $node
     * @param ...$vars
     * @return string
     */
    public function getMessage($node, ...$vars)
    {
        return $this->messages->getMessage($node, ...$vars);
    }

    /**
     * @return NoeulAPI
     */
    public function getNoeulAPI()
    {
        return $this->noeulAPI;
    }

    /**
     * @param PPGroup $group
     * @return array
     */
    public function getOnlinePlayersInGroup(PPGroup $group)
    {
        $users = [];

        foreach($this->getServer()->getOnlinePlayers() as $player)
        {
            foreach($this->getServer()->getLevels() as $level)
            {
                $levelName = $level->getName();

                if($this->userDataMgr->getGroup($player, $levelName) === $group)
                    $users[] = $player;
            }
        }

        return $users;
    }

    /**
     * @param IPlayer $player
     * @param $levelName
     * @return array
     */
    public function getPermissions(IPlayer $player, $levelName)
    {
        // TODO: Fix this damn shit
        $group = $this->userDataMgr->getGroup($player, $levelName);

        $groupPerms = $group->getGroupPermissions($levelName);
        $userPerms = $this->userDataMgr->getUserPermissions($player, $levelName);

        return array_merge($groupPerms, $userPerms);
    }

    /**
     * @param $userName
     * @return Player
     */
    public function getPlayer($userName)
    {
        $player = $this->getServer()->getPlayer($userName);

        return $player instanceof Player ? $player : $this->getServer()->getOfflinePlayer($userName);
    }

    /**
     * @return array
     */
    public function getPocketMinePerms()
    {
        if($this->pmDefaultPerms === [])
        {
            /** @var \pocketmine\permission\Permission $permission */
            foreach($this->getServer()->getPluginManager()->getPermissions() as $permission)
            {
                if(strpos($permission->getName(), DefaultPermissions::ROOT) !== false)
                    $this->pmDefaultPerms[] = $permission;
            }
        }

        return $this->pmDefaultPerms;
    }

    /**
     * @return string
     */
    public function getPPVersion()
    {
        return $this->getDescription()->getVersion();
    }

    /**
     * @return ProviderInterface
     */
    public function getProvider()
    {
        if(!$this->isValidProvider())
            $this->setProvider(false);

        return $this->provider;
    }

    /**
     * @return UserDataManager
     */
    public function getUserDataMgr()
    {
        return $this->userDataMgr;
    }

    /**
     * @param Player $player
     * @return null|string
     */
    public function getValidUUID(Player $player)
    {
        $uuid = $player->getUniqueId();

        if($uuid instanceof UUID)
            return $uuid->toString();

        // Heheheh...
        $this->getLogger()->warning("Why did you give me an invalid unique id? *cries* (userName: " . $player->getName() . ", isConnected: " . $player->isConnected() . ", isOnline: " . $player->isOnline() . ", isValid: " . $player->isValid() .  ")");

        return null;
    }

    /**
     * @param $groupName
     * @return int
     */
    public function isValidGroupName($groupName)
    {
        return preg_match('/[0-9a-zA-Z\xA1-\xFE]$/', $groupName);
    }

    /**
     * @return bool
     */
    public function isValidProvider()
    {
        if(!isset($this->provider) || $this->provider === null || !($this->provider instanceof ProviderInterface))
            return false;

        return true;
    }

    /**
     * @param Player $player
     */
    public function registerPlayer(Player $player)
    {
        $this->getLogger()->debug($this->getMessage("logger_messages.registerPlayer", $player->getName()));

        $uniqueId = $this->getValidUUID($player);

        if(!isset($this->attachments[$uniqueId]))
        {
            $attachment = $player->addAttachment($this);

            $this->attachments[$uniqueId] = $attachment;

            $this->updatePermissions($player);
        }
    }

    public function registerPlayers()
    {
        foreach($this->getServer()->getOnlinePlayers() as $player)
        {
            $this->registerPlayer($player);
        }
    }

    public function reload()
    {
        $this->reloadConfig();
        $this->saveDefaultConfig();

        $this->messages->reloadMessages();

        $this->setProvider(false);

        foreach($this->getServer()->getOnlinePlayers() as $player)
        {
            $this->updatePermissions($player);
        }
    }

    /**
     * @param $groupName
     * @return bool
     */
    public function removeGroup($groupName)
    {
        if(!$this->isValidGroupName($groupName))
            return self::INVALID_NAME;

        $groupsData = $this->getProvider()->getGroupsData();

        if(!isset($groupsData[$groupName]))
            return self::NOT_FOUND;

        unset($groupsData[$groupName]);

        $this->getProvider()->setGroupsData($groupsData);

        $this->updateGroups();

        return self::SUCCESS;
    }

    /**
     * @param PPGroup $group
     */
    public function setDefaultGroup(PPGroup $group)
    {
        foreach($this->getGroups() as $currentGroup)
        {
            $isDefault = $currentGroup->getNode("isDefault");

            if($isDefault)
                $currentGroup->removeNode("isDefault");
        }

        $group->setDefault();
    }

    /**
     * @param IPlayer $player
     * @param PPGroup $group
     * @param null $levelName
     */
    public function setGroup(IPlayer $player, PPGroup $group, $levelName = null)
    {
        $this->userDataMgr->setGroup($player, $group, $levelName);
    }

    public function sortGroupData()
    {
        foreach($this->getGroups() as $groupName => $ppGroup)
        {
            $ppGroup->sortPermissions();

            if($this->getConfigValue("enable-multiworld-perms"))
            {
                /** @var Level $level */
                foreach($this->getServer()->getLevels() as $level)
                {
                    $levelName = $level->getName();

                    $ppGroup->createWorldData($levelName);
                }
            }
        }
    }

    public function updateGroups()
    {
        if(!$this->isValidProvider())
            throw new \RuntimeException("Failed to load groups: Invalid Data Provider");

        // Make group list empty first to reload it
        $this->groups = [];

        foreach(array_keys($this->getProvider()->getGroupsData()) as $groupName)
        {
            $this->groups[$groupName] = new PPGroup($this, $groupName);
        }

        $this->isGroupsLoaded = true;

        $this->sortGroupData();
    }

    /**
     * @param IPlayer $player
     */
    public function updatePermissions(IPlayer $player)
    {
        if($player instanceof Player)
        {
            $levelName = $this->getConfigValue("enable-multiworld-perms") ? $player->getLevel()->getName() : null;

            $permissions = [];

            foreach($this->getPermissions($player, $levelName) as $permission)
            {
                if($permission === '*')
                {
                    foreach($this->getServer()->getPluginManager()->getPermissions() as $tmp)
                    {
                        $permissions[$tmp->getName()] = true;
                    }
                }
                else
                {
                    $isNegative = substr($permission, 0, 1) === "-";

                    if($isNegative)
                        $permission = substr($permission, 1);

                    $permissions[$permission] = !$isNegative;
                }
            }

            $permissions[self::CORE_PERM] = true;

            /** @var \pocketmine\permission\PermissionAttachment $attachment */
            $attachment = $this->getAttachment($player);

            $attachment->clearPermissions();

            $attachment->setPermissions($permissions);
        }
    }

    /**
     * @param PPGroup $group
     */
    public function updatePlayersInGroup(PPGroup $group)
    {
        foreach($this->getServer()->getOnlinePlayers() as $player)
        {
            if($this->userDataMgr->getGroup($player) === $group)
                $this->updatePermissions($player);
        }
    }

    /**
     * @param Player $player
     */
    public function unregisterPlayer(Player $player)
    {
        $this->getLogger()->debug($this->getMessage("logger_messages.unregisterPlayer", $player->getName()));

        $uniqueId = $this->getValidUUID($player);

        // Do not try to remove attachments with invalid unique ids
        if($uniqueId !== null)
        {
            if(isset($this->attachments[$uniqueId]))
                $player->removeAttachment($this->attachments[$uniqueId]);

            unset($this->attachments[$uniqueId]);
        }
    }

    public function unregisterPlayers()
    {
        foreach($this->getServer()->getOnlinePlayers() as $player)
        {
            $this->unregisterPlayer($player);
        }
    }
}
