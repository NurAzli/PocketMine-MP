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

namespace pocketmine\block\inventory\window;

use pocketmine\block\Block;
use pocketmine\block\utils\AnimatedContainer;
use pocketmine\inventory\Inventory;
use pocketmine\player\InventoryWindow;
use pocketmine\player\Player;

class BlockInventoryWindow extends InventoryWindow{

	public function __construct(
		Player $viewer,
		Inventory $inventory,
		protected Block $holder
	){
		parent::__construct($viewer, $inventory);
	}

	public function getHolder() : Block{ return $this->holder; }

	public function onOpen() : void{
		parent::onOpen();
		if($this->holder instanceof AnimatedContainer){
			$this->holder->onContainerOpen();
		}
	}

	public function onClose() : void{
		if($this->holder instanceof AnimatedContainer){
			$this->holder->onContainerClose();
		}
		parent::onClose();
	}
}
