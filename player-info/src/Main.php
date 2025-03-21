<?php

declare(strict_types=1);

namespace phrqndy\playerinfo;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\form\Form;
use pocketmine\permission\DefaultPermissions;

class Main extends PluginBase implements Listener {

    /** @var array */
    private const DEVICE_MAP = [
        0 => "Android",
        1 => "iOS",
        2 => "macOS",
        3 => "FireOS",
        4 => "GearVR",
        5 => "HoloLens",
        6 => "Windows 10",
        7 => "Windows",
        8 => "Dedicated",
        9 => "tvOS",
        10 => "PlayStation",
        11 => "Nintendo Switch",
        12 => "Xbox",
        13 => "Windows Phone"
    ];

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info(TextFormat::GREEN . "PlayerInfo Plugin Enabled!");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "info") {
            // Check if sender is a player
            if (!$sender instanceof Player) {
                $sender->sendMessage(TextFormat::RED . "This command can only be used in-game.");
                return false;
            }

            // Check if player has permission
            if (!$sender->hasPermission("playerinfo.command")) {
                $sender->sendMessage(TextFormat::RED . "You don't have permission to use this command.");
                return false;
            }

            // Check if argument is provided
            if (count($args) < 1) {
                $sender->sendMessage(TextFormat::RED . "Usage: /info {player}");
                return false;
            }

            $playerName = $args[0];
            $targetPlayer = $this->getServer()->getPlayerByPrefix($playerName);

            if ($targetPlayer === null) {
                $sender->sendMessage(TextFormat::RED . "Player not found or is offline.");
                return false;
            }

            $this->sendPlayerInfoForm($sender, $targetPlayer);
            return true;
        }
        return false;
    }

    /**
     * Formats a UUID into a standard, human-readable format
     */
    private function formatUUID(string $uuid): string {
        // Remove any hyphens that might already exist
        $uuid = str_replace('-', '', $uuid);

        // Format to standard UUID format (8-4-4-4-12)
        if (strlen($uuid) == 32) {
            return substr($uuid, 0, 8) . '-' .
                substr($uuid, 8, 4) . '-' .
                substr($uuid, 12, 4) . '-' .
                substr($uuid, 16, 4) . '-' .
                substr($uuid, 20);
        }

        return $uuid; // Return as is if not a standard UUID length
    }


    private function analyzePlayerInfo(Player $player): array {
        $info = [];

        // Basic info
        $info['username'] = $player->getName();
        $info['display_name'] = $player->getDisplayName();
        $info['ip_address'] = $player->getNetworkSession()->getIp();
        $info['ping'] = $player->getNetworkSession()->getPing();

        // UUID formatting
        $info['uuid'] = $this->formatUUID($player->getUniqueId()->toString());
        $info['xuid'] = $player->getXuid();

        // Device info
        $extraData = $player->getPlayerInfo()->getExtraData();
        $deviceOS = $extraData["DeviceOS"] ?? -1;
        $info['device'] = self::DEVICE_MAP[$deviceOS] ?? "Unknown";

        // Device model and ID
        $info['device_model'] = $extraData["DeviceModel"] ?? "Unknown";
        $info['device_id'] = $extraData["DeviceId"] ?? "Unknown";

        // Game version and UI profile
        $info['game_version'] = $extraData["GameVersion"] ?? "Unknown";
        $info['ui_profile'] = $extraData["UIProfile"] ?? "Classic";

        // Default client to Mojang
        $info['client'] = "Mojang (Default)";

        // Additional player stats
        $info['health'] = $player->getHealth();
        $info['max_health'] = $player->getMaxHealth();

        // Fix for checking operator status - use hasPermission instead of isOp()
        $info['op_status'] = $player->hasPermission(DefaultPermissions::ROOT_OPERATOR) ? "Operator" : "Regular Player";

        $info['gamemode'] = $player->getGamemode()->name;

        return $info;
    }

    private function sendPlayerInfoForm(Player $sender, Player $target): void {
        // Get all player information
        $playerInfo = $this->analyzePlayerInfo($target);

        // Create the content string with player information
        $content = "§6Username:§f " . $playerInfo['username'] . "\n\n";
        $content .= "§6Display Name:§f " . $playerInfo['display_name'] . "\n\n";
        $content .= "§6UUID:§f " . $playerInfo['uuid'] . "\n\n";
        if (!empty($playerInfo['xuid'])) {
            $content .= "§6XUID:§f " . $playerInfo['xuid'] . "\n\n";
        }
        $content .= "§6Device:§f " . $playerInfo['device'] . "\n\n";
        $content .= "§6Device Model:§f " . $playerInfo['device_model'] . "\n\n";
        $content .= "§6Game Version:§f " . $playerInfo['game_version'] . "\n\n";
        $content .= "§6Client:§f " . $playerInfo['client'] . "\n\n";
        $content .= "§6IP Address:§f " . $playerInfo['ip_address'] . "\n\n";
        $content .= "§6Ping:§f " . $playerInfo['ping'] . "ms\n\n";
        $content .= "§6Gamemode:§f " . $playerInfo['gamemode'] . "\n\n";
        $content .= "§6Health:§f " . $playerInfo['health'] . "/" . $playerInfo['max_health'] . "\n\n";
        $content .= "§6Status:§f " . $playerInfo['op_status'];

        // Send custom form
        $form = new class($playerInfo['username'], $content) implements Form {
            private string $username;
            private string $content;

            public function __construct(string $username, string $content) {
                $this->username = $username;
                $this->content = $content;
            }

            public function jsonSerialize(): array {
                return [
                    "type" => "form",
                    "title" => "Player Information: " . $this->username,
                    "content" => $this->content,
                    "buttons" => [
                        [
                            "text" => "Close"
                        ]
                    ]
                ];
            }

            public function handleResponse(Player $player, $data): void {
                // Form closed or "Close" button clicked - no additional action needed
            }
        };

        $sender->sendForm($form);
    }
}