<?php

namespace Cosmic5173\Knockback;

/*
 *   KnockbackSettings - Edit server knockback settings.
 *   Copyright (C) 2022  Armen Deroian
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

use JetBrains\PhpStorm\Pure;
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginOwned;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

final class Main extends PluginBase {

    private static Main $instance;
    private Config $database;

    public static function getInstance(): Main {
        return self::$instance;
    }

    public function getDatabase(): Config {
        return $this->database;
    }

    protected function onEnable(): void {
        $this->saveDefaultConfig();
        self::$instance = $this;
        $this->database = $this->getConfig();
        $this->getServer()->getPluginManager()->registerEvents(new class() implements Listener {

            public function onPlayerCreation(PlayerCreationEvent $event): void {
                $event->setPlayerClass(KBPlayer::class);
            }

            public function onEntityDamageByEntityEvent(EntityDamageByEntityEvent $event): void {
                $kb = Main::getInstance()->getKB($event->getEntity()->getPosition()->getWorld()->getFolderName());
                if ($kb) {
                    $event->setAttackCooldown($kb->delay);
                }
            }

            public function onLevelChangeEvent(EntityTeleportEvent $event): void {
                if ($event->getTo()->getWorld()->getId() !== $event->getFrom()->getWorld()->getId()) {
                    $kb = Main::getInstance()->getKB($event->getEntity()->getPosition()->getWorld()->getFolderName());
                    if ($kb) {
                        $event->getEntity()->setGravity($kb->gravity);
                    } else {
                        $event->getEntity()->setGravity(0.08);
                    }
                }
            }
        }, $this);
        $this->getServer()->getCommandMap()->register("KBPlugin", new class() extends Command implements PluginOwned {

            public function __construct() {
                parent::__construct("knockback", "Edit an arenas knockback.", "", ["kb"]);
                $this->setPermission("knockback.use");
            }

            public function execute(CommandSender $sender, string $commandLabel, array $args) {
                if (!$sender instanceof Player) return;
                if (!$this->testPermissionSilent($sender)) {
                    $sender->sendMessage(TextFormat::RED."You do not have permission to use that command.");

                    return;
                }
                $form = new SimpleForm(static function (Player $player, ?string $data) use ($sender): void {
                    if (!isset($data)) return;
                    switch ($data) {
                        case "list":
                            $kb = Main::getInstance()->getKB($player->getPosition()->getWorld()->getFolderName());
                            if (!$kb) {
                                $player->sendMessage(TextFormat::colorize("&cThere are no Knockback settings for this world."));

                                return;
                            }
                            $player->sendMessage(TextFormat::colorize("&bCurrent KB:\n\n&aXZ (Horizontal): {$kb->xz}\n&aY (Vertical): {$kb->y}\n&aDelay (Attack Delay): {$kb->delay}\n&aGravity: {$kb->gravity}"));
                            break;
                        case "create":
                            Main::getInstance()->registerKB(Main::getInstance()->createKB($player->getPosition()->getWorld()->getFolderName(), 0.4, 0.4, 8, 0.08));
                            $player->sendMessage(TextFormat::colorize("&aKnockback created."));
                            break;
                        case "edit":
                            $form = new CustomForm(static function (Player $player, ?array $data): void {
                                if (!isset($data)) {
                                    Server::getInstance()->dispatchCommand($player, "kb");

                                    return;
                                }
                                $xz = (float) $data[0];
                                $y = (float) $data[1];
                                $delay = (float) $data[2];
                                $gravity = (float) $data[3];
                                $kb = Main::getInstance()->getKB($player->getPosition()->getWorld()->getFolderName());
                                Main::getInstance()->editKB($kb->world, "xz", $xz);
                                Main::getInstance()->editKB($kb->world, "y", $y);
                                Main::getInstance()->editKB($kb->world, "delay", $delay);
                                Main::getInstance()->editKB($kb->world, "gravity", $gravity);
                                Server::getInstance()->dispatchCommand($player, "kb");
                            });
                            $kb = Main::getInstance()->getKB($player->getPosition()->getWorld()->getFolderName());
                            $form->setTitle("Edit Knockback");
                            $form->addInput("XZ (Horizontal)", "number...", $kb->xz);
                            $form->addInput("Y (Vertical)", "number...", $kb->y);
                            $form->addInput("Delay (Attack Delay)", "number...", $kb->delay);
                            $form->addInput("Gravity", "number...", $kb->gravity);
                            $sender->sendForm($form);
                    }
                });
                $form->setTitle("Knockback");
                $form->addButton("List", -1, "", "list");
                $form->addButton("Create", -1, "", "create");
                $form->addButton("Edit", -1, "", "edit");
                $form->addButton("Close", -1, "", "close");
                $sender->sendForm($form);
            }

            #[Pure]
            public function getOwningPlugin(): Main {
                return Main::getInstance();
            }
        });
    }

    protected function onDisable(): void {
        $this->getDatabase()->save();
    }

    public function createKB(string $world, float $xz, float $y, float $delay, float $gravity): object {
        return new class($world, $xz, $y, $delay, $gravity) {

            public ?string $world;
            public ?float $xz;
            public ?float $y;
            public ?float $delay;
            public ?float $gravity;

            public function __construct(?string $world, ?string $xz, ?string $y, ?string $delay, ?string $gravity) {
                $this->world = $world;
                $this->xz = $xz;
                $this->y = $y;
                $this->delay = $delay;
                $this->gravity = $gravity;
            }

            public function toArray(): array {
                return ["world" => $this->world, "xz" => $this->xz, "y" => $this->y, "delay" => $this->delay, "gravity" => $this->gravity];
            }
        };
    }

    public function registerKB(mixed $kb): void {
        $this->getDatabase()->set($kb->world, $kb->toArray());
        $this->getDatabase()->save();
    }

    public function deleteKB(string $name): void {
        $this->getDatabase()->remove($name);
    }

    public function editKB(string $name, string $value, mixed $data): void {
        $kb = $this->getDatabase()->get($name);
        $kb[$value] = $data;
        $this->getDatabase()->set($name, $kb);
        $this->getDatabase()->save();
    }

    public function getKB(string $name): object|bool {
        $kb = $this->getDatabase()->get($name);

        return $kb ? $this->createKB($kb["world"], $kb["xz"], $kb["y"], $kb["delay"], $kb["gravity"]) : $kb;
    }
}