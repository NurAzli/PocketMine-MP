<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\permission\DefaultPermissionNames;
use function count;

final class DeletePlayerDataCommand extends Command{

	public function __construct(){
		parent::__construct(
			"deleteplayerdata",
			"Deletes saved data for the specified player, like inventory and respawn position", //TODO: l10n
			"/deleteplayerdata <playerName>" //TODO: l10n
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_DELETEPLAYERDATA);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) !== 1){
			throw new InvalidCommandSyntaxException();
		}

		$playerName = $args[0];
		if($sender->getServer()->hasOfflinePlayerData($playerName)){
			$sender->getServer()->deleteOfflinePlayerData($playerName);
			Command::broadcastCommandMessage($sender, "Deleted player data for $playerName");
		}else{
			$sender->sendMessage("No data found for $playerName"); //TODO: l10n
		}

		return true;
	}
}
